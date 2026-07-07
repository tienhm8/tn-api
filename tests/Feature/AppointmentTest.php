<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentTest extends TestCase
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
     * Tạo 1 khách (marketing tạo → round-robin gán sale1).
     */
    private function makeCustomerForSale1(): Customer
    {
        $this->asUser($this->marketing)
            ->postJson('/api/v1/customers', ['company_name' => 'Công ty A', 'phone' => '0900000001'])
            ->assertStatus(201);

        return Customer::latest('id')->firstOrFail();
    }

    private function scheduleAs(User $sale, Customer $customer, Carbon $when): Appointment
    {
        $this->asUser($sale)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->id,
                'scheduled_at' => $when->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(201);

        return Appointment::latest('id')->firstOrFail();
    }

    public function test_sale_schedules_appointment_and_sets_next_appointment(): void
    {
        $customer = $this->makeCustomerForSale1();
        $when = now()->addDay()->setTime(9, 0);

        $this->asUser($this->sale1)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->id,
                'scheduled_at' => $when->format('Y-m-d H:i:s'),
                'note' => 'Gọi tư vấn ISO',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status.value', 'scheduled')
            ->assertJsonPath('data.customer.id', $customer->id);

        $customer->refresh();
        $this->assertSame($this->sale1->id, Appointment::firstOrFail()->user_id);
        $this->assertNotNull($customer->next_appointment_at);
        $this->assertSame($when->format('Y-m-d H:i'), $customer->next_appointment_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('customer_activities', ['customer_id' => $customer->id, 'type' => 'note']);
    }

    public function test_appointments_are_grouped_into_buckets_for_sale(): void
    {
        $customer = $this->makeCustomerForSale1();

        $this->scheduleAs($this->sale1, $customer, now()->subDay());
        $this->scheduleAs($this->sale1, $customer, now()->startOfDay()->addHours(10));
        $this->scheduleAs($this->sale1, $customer, now()->addDay()->startOfDay()->addHours(10));

        $this->asUser($this->sale1)
            ->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonPath('counts.overdue', 1)
            ->assertJsonPath('counts.today', 1)
            ->assertJsonPath('counts.tomorrow', 1);
    }

    public function test_sale_cannot_see_other_sales_appointments_in_buckets(): void
    {
        $customer = $this->makeCustomerForSale1(); // sale1
        $this->scheduleAs($this->sale1, $customer, now()->addDay());

        $this->asUser($this->sale2)
            ->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonPath('counts.overdue', 0)
            ->assertJsonPath('counts.today', 0)
            ->assertJsonPath('counts.tomorrow', 0)
            ->assertJsonPath('counts.upcoming', 0);
    }

    public function test_complete_appointment_logs_call_and_recomputes_next(): void
    {
        $customer = $this->makeCustomerForSale1();
        $t1 = now()->addDay();
        $t2 = now()->addDays(3);
        $a1 = $this->scheduleAs($this->sale1, $customer, $t1);
        $this->scheduleAs($this->sale1, $customer, $t2);

        $customer->refresh();
        $this->assertSame($t1->format('Y-m-d H:i'), $customer->next_appointment_at->format('Y-m-d H:i'));

        $this->asUser($this->sale1)
            ->postJson("/api/v1/appointments/{$a1->id}/complete", ['outcome' => 'Khách đồng ý ký hợp đồng'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        $this->assertDatabaseHas('customer_activities', ['customer_id' => $customer->id, 'type' => 'call']);

        $customer->refresh();
        $this->assertSame($t2->format('Y-m-d H:i'), $customer->next_appointment_at->format('Y-m-d H:i'));
    }

    public function test_sale_cannot_schedule_for_other_sales_customer(): void
    {
        $customer = $this->makeCustomerForSale1(); // sale1

        $this->asUser($this->sale2)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);
    }

    public function test_marketing_cannot_manage_appointments(): void
    {
        $customer = $this->makeCustomerForSale1();

        $this->asUser($this->marketing)
            ->postJson('/api/v1/appointments', [
                'customer_id' => $customer->id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);
    }

    public function test_add_note_appends_to_customer_timeline(): void
    {
        $customer = $this->makeCustomerForSale1();

        $this->asUser($this->sale1)
            ->postJson("/api/v1/customers/{$customer->id}/notes", ['content' => 'Đã trao đổi qua Zalo'])
            ->assertStatus(201)
            ->assertJsonPath('data.type.value', 'note')
            ->assertJsonPath('data.content', 'Đã trao đổi qua Zalo');

        $this->assertDatabaseHas('customer_activities', [
            'customer_id' => $customer->id,
            'content' => 'Đã trao đổi qua Zalo',
            'user_id' => $this->sale1->id,
        ]);
    }
}
