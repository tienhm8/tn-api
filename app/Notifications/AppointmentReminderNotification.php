<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $customer = $this->appointment->customer;

        return [
            'type' => 'appointment_reminder',
            'appointment_id' => $this->appointment->id,
            'customer_id' => $customer?->id,
            'customer_code' => $customer?->code,
            'company_name' => $customer?->company_name,
            'scheduled_at' => optional($this->appointment->scheduled_at)->toIso8601String(),
            'message' => 'Đến giờ gọi lại: '.($customer?->company_name ?? 'khách hàng')
                .' lúc '.optional($this->appointment->scheduled_at)->format('H:i d/m/Y'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
