<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AppointmentReminderNotification;
use App\Services\AuthService;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $sale1;

    private User $sale2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, ServiceSeeder::class, SettingSeeder::class]);

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

    private function appointmentFor(User $sale, \DateTimeInterface|string $scheduledAt): Appointment
    {
        $customer = Customer::factory()->assignedTo($sale->id)->create();

        return Appointment::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $sale->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'reminder_sent_at' => null,
        ]);
    }

    public function test_due_appointment_reminds_sale_and_marks_sent(): void
    {
        Notification::fake();
        $appt = $this->appointmentFor($this->sale1, now()->subMinute());

        $this->artisan('reminders:dispatch')->assertSuccessful();

        Notification::assertSentTo($this->sale1, AppointmentReminderNotification::class);
        $this->assertNotNull($appt->fresh()->reminder_sent_at);
    }

    public function test_future_appointment_beyond_lead_is_not_reminded(): void
    {
        Notification::fake();
        $this->appointmentFor($this->sale1, now()->addHours(2));

        $this->artisan('reminders:dispatch')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_lead_time_reminds_before_scheduled(): void
    {
        Notification::fake();
        app(SettingService::class)->put('reminder_lead_minutes', '10');
        // Lịch còn 5 phút nữa → nằm trong ngưỡng nhắc trước 10 phút.
        $this->appointmentFor($this->sale1, now()->addMinutes(5));

        $this->artisan('reminders:dispatch')->assertSuccessful();

        Notification::assertSentTo($this->sale1, AppointmentReminderNotification::class);
    }

    public function test_reminder_is_not_sent_twice(): void
    {
        Notification::fake();
        $this->appointmentFor($this->sale1, now()->subMinute());

        $this->artisan('reminders:dispatch');
        $this->artisan('reminders:dispatch');

        Notification::assertSentToTimes($this->sale1, AppointmentReminderNotification::class, 1);
    }

    public function test_user_lists_and_marks_all_notifications_read(): void
    {
        $this->seedNotification($this->sale1, 'Đến giờ gọi lại: Công ty A');

        $this->asUser($this->sale1)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.type', 'appointment_reminder')
            ->assertJsonPath('data.0.message', 'Đến giờ gọi lại: Công ty A');

        $this->asUser($this->sale1)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_mark_single_notification_read(): void
    {
        $n = $this->seedNotification($this->sale1, 'Test');

        $this->asUser($this->sale1)
            ->postJson("/api/v1/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($this->sale1->notifications()->first()->read_at);
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $this->seedNotification($this->sale1, 'Của sale1');

        $this->asUser($this->sale2)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'data');
    }

    private function seedNotification(User $user, string $message): \Illuminate\Notifications\DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AppointmentReminderNotification::class,
            'data' => ['type' => 'appointment_reminder', 'message' => $message],
            'read_at' => null,
        ]);
    }
}
