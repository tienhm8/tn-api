<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use App\Repositories\Appointment\AppointmentRepositoryInterface;

class ReminderService
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
    ) {}

    /**
     * Nhắc các lịch chăm sóc đến hạn (trước `reminder_lead_minutes` phút).
     * Gửi notification cho sale phụ trách + đánh dấu `reminder_sent_at` để chống nhắc trùng.
     *
     * @return int Số lịch đã nhắc.
     */
    public function dispatchDue(): int
    {
        $lead = (int) setting('reminder_lead_minutes', 0);
        $due = $this->appointments->dueForReminder(now()->addMinutes($lead));

        $due->each(function (Appointment $appointment): void {
            $appointment->user?->notify(new AppointmentReminderNotification($appointment));
            $appointment->forceFill(['reminder_sent_at' => now()])->save();
        });

        return $due->count();
    }
}
