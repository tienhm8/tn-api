<?php

namespace App\Repositories\Appointment;

use App\Models\Appointment;
use App\Models\User;
use App\Repositories\RepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface extends RepositoryInterface
{
    /**
     * Các lịch chăm sóc đang chờ (status=scheduled) chia 4 rổ theo thời gian,
     * đã scope theo role của user.
     *
     * @return array{overdue: Collection<int, Appointment>, today: Collection<int, Appointment>, tomorrow: Collection<int, Appointment>, upcoming: Collection<int, Appointment>}
     */
    public function bucketsForUser(User $user): array;

    /**
     * Thời điểm lịch chăm sóc đang chờ sớm nhất của một khách (để cập nhật next_appointment_at).
     */
    public function minScheduledAt(int $customerId): ?string;

    /**
     * Các lịch chăm sóc đến hạn nhắc: status=scheduled, chưa nhắc, scheduled_at <= $threshold.
     * Kèm customer + sale phụ trách.
     *
     * @return Collection<int, Appointment>
     */
    public function dueForReminder(Carbon $threshold): Collection;
}
