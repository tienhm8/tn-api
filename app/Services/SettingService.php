<?php

namespace App\Services;

use App\Models\Setting;
use App\Repositories\Setting\SettingRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public function __construct(
        private SettingRepositoryInterface $settings,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    public function put(string $key, ?string $value): void
    {
        $this->settings->put($key, $value);
    }

    /**
     * Sinh mã khách hàng tiếp theo dạng `KH000123` từ counter atomic.
     */
    public function nextCustomerCode(): string
    {
        $seq = $this->nextSequence('customer_code_seq');

        return 'KH'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Tăng atomic một counter trong bảng settings (lockForUpdate), trả giá trị mới.
     */
    public function nextSequence(string $key): int
    {
        $next = DB::transaction(function () use ($key): int {
            $row = Setting::where('key', $key)->lockForUpdate()->first();
            if (! $row) {
                $row = new Setting(['key' => $key]);
            }
            $value = ((int) $row->value) + 1;
            $row->value = (string) $value;
            $row->save();

            return $value;
        });

        $this->settings->forgetKey($key);

        return $next;
    }

    /**
     * Xoay vòng con trỏ (round-robin) trên một tập id đã sắp thứ tự; trả id kế tiếp.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function rotateCursor(string $key, array $orderedIds): ?int
    {
        if (empty($orderedIds)) {
            return null;
        }

        $picked = DB::transaction(function () use ($key, $orderedIds): int {
            $row = Setting::where('key', $key)->lockForUpdate()->first();
            if (! $row) {
                $row = new Setting(['key' => $key]);
            }
            $last = ($row->value !== null && $row->value !== '') ? (int) $row->value : null;
            $next = $this->pickNext($orderedIds, $last);
            $row->value = (string) $next;
            $row->save();

            return $next;
        });

        $this->settings->forgetKey($key);

        return $picked;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function pickNext(array $ids, ?int $last): int
    {
        if ($last === null) {
            return $ids[0];
        }

        $idx = array_search($last, $ids, true);
        if ($idx === false) {
            return $ids[0];
        }

        return $ids[($idx + 1) % count($ids)];
    }
}
