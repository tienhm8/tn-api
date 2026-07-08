<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $marketing;

    private User $sale1;

    private User $sale2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, ServiceSeeder::class, SettingSeeder::class]);

        $this->admin = $this->makeUser('admin@test.local', 'admin');
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

    public function test_admin_dashboard_reports_totals(): void
    {
        Customer::factory()->count(3)->create();

        $this->asUser($this->admin)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.stats.0.key', 'total')
            ->assertJsonPath('data.stats.0.value', 3)
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'stats',
                    'reminders' => ['overdue', 'today', 'tomorrow'],
                    'breakdown' => ['by_status', 'top_services'],
                ],
            ]);
    }

    public function test_sale_dashboard_is_scoped_to_assigned(): void
    {
        Customer::factory()->assignedTo($this->sale1->id)->count(2)->create();
        Customer::factory()->assignedTo($this->sale2->id)->create();

        $this->asUser($this->sale1)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.role', 'sale')
            ->assertJsonPath('data.stats.0.key', 'assigned')
            ->assertJsonPath('data.stats.0.value', 2);
    }

    public function test_marketing_dashboard_is_scoped_to_own(): void
    {
        Customer::factory()->count(2)->create(['created_by' => $this->marketing->id, 'lead_source' => 'facebook']);
        Customer::factory()->create(['created_by' => $this->marketing->id, 'lead_source' => 'zalo']);
        Customer::factory()->create(); // của người khác

        $this->asUser($this->marketing)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.role', 'marketing')
            ->assertJsonPath('data.stats.0.key', 'total')
            ->assertJsonPath('data.stats.0.value', 3)
            ->assertJsonStructure(['data' => ['breakdown' => ['by_lead_source']]]);
    }

    public function test_admin_reads_and_updates_settings(): void
    {
        $this->asUser($this->admin)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.reminder_lead_minutes', 0);

        $this->asUser($this->admin)
            ->putJson('/api/v1/settings', ['reminder_lead_minutes' => 15])
            ->assertOk()
            ->assertJsonPath('data.reminder_lead_minutes', 15);

        $this->asUser($this->admin)
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.reminder_lead_minutes', 15);
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $this->asUser($this->sale1)->getJson('/api/v1/settings')->assertStatus(403);
        $this->asUser($this->sale1)->putJson('/api/v1/settings', ['reminder_lead_minutes' => 5])->assertStatus(403);
    }

    public function test_settings_rejects_negative_lead(): void
    {
        $this->asUser($this->admin)
            ->putJson('/api/v1/settings', ['reminder_lead_minutes' => -1])
            ->assertStatus(422);
    }
}
