<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\CustomerAssignedNotification;
use App\Services\AuthService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Công ty A',
            'phone' => '0900000001',
            'service_ids' => [1, 2],
        ], $overrides);
    }

    public function test_marketing_creates_customer_and_round_robin_assigns_sales(): void
    {
        $first = $this->asUser($this->marketing)
            ->postJson('/api/v1/customers', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.status.value', 'assigned')
            ->assertJsonPath('data.code', 'KH000001');

        $customer = Customer::firstOrFail();
        $this->assertSame($this->sale1->id, $customer->assigned_to);
        $this->assertCount(2, $customer->services);
        $this->assertSame($this->marketing->id, $customer->created_by);

        // Khách thứ 2 → round-robin sang sale2
        $this->asUser($this->marketing)
            ->postJson('/api/v1/customers', $this->payload(['phone' => '0900000002']))
            ->assertStatus(201);
        $this->assertSame($this->sale2->id, Customer::latest('id')->firstOrFail()->assigned_to);

        // Khách thứ 3 → vòng lại sale1
        $this->asUser($this->marketing)
            ->postJson('/api/v1/customers', $this->payload(['phone' => '0900000003']))
            ->assertStatus(201);
        $this->assertSame($this->sale1->id, Customer::latest('id')->firstOrFail()->assigned_to);
    }

    public function test_marketing_sees_only_own_customers(): void
    {
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $this->asUser($this->admin)->postJson('/api/v1/customers', $this->payload(['phone' => '0900000009']))->assertStatus(201);

        $this->asUser($this->marketing)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_sale_sees_only_assigned_customers(): void
    {
        // 2 khách → sale1, sale2
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload(['phone' => '0900000002']))->assertStatus(201);

        $this->asUser($this->sale1)->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 1);
        $this->asUser($this->sale2)->getJson('/api/v1/customers')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_sale_cannot_create_customer(): void
    {
        $this->asUser($this->sale1)
            ->postJson('/api/v1/customers', $this->payload())
            ->assertStatus(403);
    }

    public function test_sale_cannot_view_other_sales_customer(): void
    {
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $customer = Customer::firstOrFail(); // assigned to sale1

        $this->asUser($this->sale2)->getJson("/api/v1/customers/{$customer->id}")->assertStatus(403);
        $this->asUser($this->sale1)->getJson("/api/v1/customers/{$customer->id}")->assertOk();
    }

    public function test_admin_can_reassign_and_notifies_new_sale(): void
    {
        Notification::fake();

        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $customer = Customer::firstOrFail(); // sale1

        $this->asUser($this->admin)
            ->postJson("/api/v1/customers/{$customer->id}/reassign", ['sale_id' => $this->sale2->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $this->sale2->id);

        Notification::assertSentTo($this->sale2, CustomerAssignedNotification::class);
    }

    public function test_non_admin_cannot_reassign(): void
    {
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $customer = Customer::firstOrFail();

        $this->asUser($this->marketing)
            ->postJson("/api/v1/customers/{$customer->id}/reassign", ['sale_id' => $this->sale2->id])
            ->assertStatus(403);
    }

    public function test_change_status_to_lost_requires_reason(): void
    {
        $this->asUser($this->marketing)->postJson('/api/v1/customers', $this->payload())->assertStatus(201);
        $customer = Customer::firstOrFail(); // assigned sale1

        $this->asUser($this->sale1)
            ->postJson("/api/v1/customers/{$customer->id}/status", ['status' => 'lost'])
            ->assertStatus(422);

        $this->asUser($this->sale1)
            ->postJson("/api/v1/customers/{$customer->id}/status", ['status' => 'lost', 'lost_reason' => 'no_need'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'lost')
            ->assertJsonPath('data.lost_reason.value', 'no_need');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/customers')->assertStatus(401);
    }
}
