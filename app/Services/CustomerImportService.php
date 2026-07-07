<?php

namespace App\Services;

use App\Enums\CustomerSource;
use App\Enums\LeadSource;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class CustomerImportService
{
    /**
     * Cột chuẩn của file import (thứ tự dùng cho file mẫu).
     *
     * @var array<int, string>
     */
    private const COLUMNS = [
        'company_name',
        'phone',
        'email',
        'contact_name',
        'address',
        'lead_source',
        'services',
        'initial_note',
        'marketing_note',
    ];

    public function __construct(
        private CustomerService $customerService,
    ) {}

    /**
     * Import khách hàng từ file Excel/CSV. Mỗi dòng tạo 1 khách (round-robin gán sale).
     *
     * @return array{total: int, imported: int, failed: int, errors: array<int, array{row: int, messages: array<int, string>}>}
     */
    public function import(UploadedFile $file, User $creator): array
    {
        $rows = $this->readRows($file);
        $serviceMaps = $this->buildServiceMaps();

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 cho header, +1 vì index bắt đầu từ 0

            $data = [
                'company_name' => $row['company_name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'contact_name' => $row['contact_name'] ?? null,
                'address' => $row['address'] ?? null,
                'lead_source' => $this->resolveLeadSource($row['lead_source'] ?? null),
                'initial_note' => $row['initial_note'] ?? null,
                'marketing_note' => $row['marketing_note'] ?? null,
            ];

            $validator = Validator::make($data, [
                'company_name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
            ], [
                'company_name.required' => 'Thiếu tên công ty.',
                'phone.required' => 'Thiếu số điện thoại.',
                'email.email' => 'Email không hợp lệ.',
            ]);

            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'messages' => $validator->errors()->all()];

                continue;
            }

            $data['service_ids'] = $this->resolveServiceIds($row['services'] ?? null, $serviceMaps);

            try {
                $this->customerService->create($data, $creator, CustomerSource::Import);
                $imported++;
            } catch (\Throwable $e) {
                Log::error('Customer import row failed', ['row' => $rowNumber, 'error' => $e->getMessage()]);
                $errors[] = ['row' => $rowNumber, 'messages' => ['Lỗi hệ thống khi tạo khách hàng.']];
            }
        }

        return [
            'total' => count($rows),
            'imported' => $imported,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Tạo nội dung file Excel mẫu (header + 1 dòng ví dụ) trong bộ nhớ.
     *
     * Ghi ra file tạm rồi đọc + xóa ngay để không rò rỉ file (không phụ thuộc
     * deleteFileAfterSend — vốn không chạy nếu client ngắt kết nối giữa chừng).
     */
    public function buildTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tn_tpl_');

        try {
            $writer = new XlsxWriter;
            $writer->openToFile($path);
            $writer->addRow(SpoutRow::fromValues(self::COLUMNS));
            $writer->addRow(SpoutRow::fromValues([
                'Công ty ABC',
                '0901234567',
                'abc@example.com',
                'Nguyễn Văn A',
                '123 Đường X, Quận 1',
                'zalo',
                'iso_9001_2015;haccp',
                'Khách cần tư vấn chứng nhận ISO',
                'Ưu tiên gọi buổi sáng',
            ]));
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Đọc file thành mảng các dòng (assoc theo cột chuẩn).
     *
     * @return array<int, array<string, string|null>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = $extension === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($file->getRealPath());

        $rows = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($headers === null) {
                    $headers = array_map(fn ($cell) => $this->canonicalHeader((string) $cell), $cells);

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $columnIndex => $key) {
                    if ($key === null) {
                        continue;
                    }
                    $value = $cells[$columnIndex] ?? null;
                    $assoc[$key] = $value === null ? null : trim((string) $value);
                }

                $rows[] = $assoc;
            }

            break; // chỉ sheet đầu tiên
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Chuẩn hóa header về tên cột chuẩn (hỗ trợ tiếng Việt & tiếng Anh), null nếu không nhận diện.
     */
    private function canonicalHeader(string $header): ?string
    {
        $key = mb_strtolower(trim($header));

        $aliases = [
            'company_name' => 'company_name', 'tên công ty' => 'company_name', 'công ty' => 'company_name', 'ten cong ty' => 'company_name',
            'phone' => 'phone', 'số điện thoại' => 'phone', 'điện thoại' => 'phone', 'sdt' => 'phone', 'so dien thoai' => 'phone',
            'email' => 'email',
            'contact_name' => 'contact_name', 'người liên hệ' => 'contact_name', 'liên hệ' => 'contact_name', 'nguoi lien he' => 'contact_name',
            'address' => 'address', 'địa chỉ' => 'address', 'dia chi' => 'address',
            'lead_source' => 'lead_source', 'nguồn' => 'lead_source', 'nguồn khách' => 'lead_source', 'nguon' => 'lead_source',
            'services' => 'services', 'dịch vụ' => 'services', 'dịch vụ cần làm' => 'services', 'dich vu' => 'services',
            'initial_note' => 'initial_note', 'nội dung khách để lại' => 'initial_note', 'nội dung' => 'initial_note', 'noi dung' => 'initial_note',
            'marketing_note' => 'marketing_note', 'ghi chú marketing' => 'marketing_note', 'ghi chú' => 'marketing_note', 'ghi chu' => 'marketing_note',
        ];

        return $aliases[$key] ?? null;
    }

    /**
     * @return array{byCode: array<string, int>, byName: array<string, int>}
     */
    private function buildServiceMaps(): array
    {
        $byCode = [];
        $byName = [];

        Service::query()->get(['id', 'code', 'name'])->each(function (Service $service) use (&$byCode, &$byName) {
            $byCode[mb_strtolower($service->code)] = $service->id;
            $byName[mb_strtolower($service->name)] = $service->id;
        });

        return ['byCode' => $byCode, 'byName' => $byName];
    }

    /**
     * Tách chuỗi dịch vụ (phân tách bằng `;` hoặc `,`) và map sang id theo code hoặc tên.
     *
     * @param  array{byCode: array<string, int>, byName: array<string, int>}  $maps
     * @return array<int, int>
     */
    private function resolveServiceIds(?string $servicesCell, array $maps): array
    {
        if ($servicesCell === null || trim($servicesCell) === '') {
            return [];
        }

        $tokens = preg_split('/[;,]/', $servicesCell) ?: [];
        $ids = [];

        foreach ($tokens as $token) {
            $key = mb_strtolower(trim($token));
            if ($key === '') {
                continue;
            }
            $id = $maps['byCode'][$key] ?? $maps['byName'][$key] ?? null;
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Chuẩn hóa nguồn khách từ code hoặc nhãn hiển thị; null nếu không khớp.
     */
    private function resolveLeadSource(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $key = mb_strtolower(trim($value));

        foreach (LeadSource::cases() as $case) {
            if ($key === $case->value || $key === mb_strtolower($case->label())) {
                return $case->value;
            }
        }

        return null;
    }
}
