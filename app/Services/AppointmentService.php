<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\Appointment\AppointmentRepositoryInterface;
use App\Repositories\CustomerActivity\CustomerActivityRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private CustomerActivityRepositoryInterface $activities,
    ) {}

    /**
     * Đặt lịch chăm sóc / gọi lại cho khách + cập nhật next_appointment_at.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Customer $customer, array $data, User $actor): Appointment
    {
        $appointment = DB::transaction(function () use ($customer, $data, $actor): Appointment {
            /** @var Appointment $appointment */
            $appointment = $this->appointments->create([
                'customer_id' => $customer->id,
                'user_id' => $data['user_id'] ?? $customer->assigned_to ?? $actor->id,
                'scheduled_at' => $data['scheduled_at'],
                'note' => $data['note'] ?? null,
                'status' => AppointmentStatus::Scheduled->value,
            ]);

            $this->refreshNextAppointment($customer);

            $this->activities->log(
                $customer->id,
                $actor->id,
                ActivityType::Note,
                'Đặt lịch chăm sóc lúc '.$this->fmt($appointment->scheduled_at)
                    .($appointment->note ? ' — '.$appointment->note : '')
            );

            return $appointment;
        });

        return $appointment->load(['user:id,name', 'customer:id,code,company_name,phone,status']);
    }

    /**
     * Đổi lịch / sửa nội dung chăm sóc.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Appointment $appointment, array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            $appointment->fill($data)->save();

            $this->refreshNextAppointment($appointment->customer);

            $this->activities->log(
                $appointment->customer_id,
                $actor->id,
                ActivityType::Note,
                'Đổi lịch chăm sóc sang '.$this->fmt($appointment->scheduled_at)
            );

            return $appointment->fresh(['user:id,name', 'customer:id,code,company_name,phone,status']);
        });
    }

    /**
     * Hoàn thành lịch (đã gọi/chăm sóc) + ghi kết quả.
     */
    public function complete(Appointment $appointment, ?string $outcome, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $outcome, $actor): Appointment {
            $appointment->status = AppointmentStatus::Completed;
            $appointment->outcome = $outcome;
            $appointment->completed_at = now();
            $appointment->save();

            $this->refreshNextAppointment($appointment->customer);

            $this->activities->log(
                $appointment->customer_id,
                $actor->id,
                ActivityType::Call,
                'Hoàn thành chăm sóc'.($outcome ? ': '.$outcome : '')
            );

            return $appointment->fresh(['user:id,name', 'customer:id,code,company_name,phone,status']);
        });
    }

    /**
     * Hủy lịch chăm sóc.
     */
    public function cancel(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor): Appointment {
            $appointment->status = AppointmentStatus::Cancelled;
            $appointment->save();

            $this->refreshNextAppointment($appointment->customer);

            $this->activities->log(
                $appointment->customer_id,
                $actor->id,
                ActivityType::Note,
                'Hủy lịch chăm sóc lúc '.$this->fmt($appointment->scheduled_at)
            );

            return $appointment->fresh(['user:id,name', 'customer:id,code,company_name,phone,status']);
        });
    }

    /**
     * Đồng bộ next_appointment_at trên khách = lịch chờ sớm nhất (null nếu không còn).
     */
    private function refreshNextAppointment(Customer $customer): void
    {
        $customer->next_appointment_at = $this->appointments->minScheduledAt($customer->id);
        $customer->save();
    }

    private function fmt(?Carbon $at): string
    {
        return $at ? $at->format('d/m/Y H:i') : '—';
    }
}
