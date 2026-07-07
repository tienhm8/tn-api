<?php

namespace Tests\Feature;

use App\Enums\CustomerSource;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    private User $marketing;

    private User $sale1;

    private User $sale2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, ServiceSeeder::class, SettingSeeder::class]);

        $this->marketing = $this->makeUser('mkt@test.local', 'marketing');
        $this->sale1 = $this->makeUser('s1@test.local', 'sale');
        $this->sale2 = $this->makeUser('s2@test.local', 'sale');
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::factory()->create(['email' => $email, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): static
    {
        return $this->withToken(app(AuthService::class)->issueToken($user)['token']);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(SpoutRow::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'import.xlsx', null, null, true);
    }

    public function test_import_creates_customers_with_round_robin_and_services(): void
    {
        $file = $this->makeXlsx([
            ['company_name', 'phone', 'email', 'contact_name', 'address', 'lead_source', 'services', 'initial_note', 'marketing_note'],
            ['Công ty A', '0900000001', 'a@x.com', 'Anh A', 'HN', 'zalo', 'iso_9001_2015;haccp', 'Cần ISO', 'Gọi sáng'],
            ['Công ty B', '0900000002', '', 'Anh B', 'HCM', 'facebook', 'haccp', '', ''],
        ]);

        $response = $this->asUser($this->marketing)
            ->post('/api/v1/customers/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk();

        $response->assertJson(['total' => 2, 'imported' => 2, 'failed' => 0]);

        $this->assertSame(2, Customer::count());

        $a = Customer::where('company_name', 'Công ty A')->firstOrFail();
        $this->assertSame(CustomerSource::Import, $a->source);
        $this->assertSame($this->sale1->id, $a->assigned_to);
        $this->assertCount(2, $a->services);

        $b = Customer::where('company_name', 'Công ty B')->firstOrFail();
        $this->assertSame($this->sale2->id, $b->assigned_to);
        $this->assertCount(1, $b->services);
    }

    public function test_import_reports_invalid_rows(): void
    {
        $file = $this->makeXlsx([
            ['company_name', 'phone', 'email'],
            ['Công ty Hợp lệ', '0900000001', 'ok@x.com'],
            ['Thiếu SĐT', '', 'bad@x.com'],
            ['', '0900000003', ''],
        ]);

        $response = $this->asUser($this->marketing)
            ->post('/api/v1/customers/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk();

        $response->assertJson(['total' => 3, 'imported' => 1, 'failed' => 2]);
        $this->assertSame(1, Customer::count());
    }

    public function test_template_download_returns_xlsx(): void
    {
        $response = $this->asUser($this->marketing)->get('/api/v1/customers/import/template');

        $response->assertOk();
        $this->assertStringContainsString('mau_import_khach_hang.xlsx', $response->headers->get('content-disposition') ?? '');
    }

    public function test_sale_cannot_import(): void
    {
        $file = $this->makeXlsx([
            ['company_name', 'phone'],
            ['Công ty A', '0900000001'],
        ]);

        $this->asUser($this->sale1)
            ->post('/api/v1/customers/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(403);
    }
}
