<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->manages($user, $appointment);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->manages($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->manages($user, $appointment);
    }

    /**
     * admin → tất cả; sale → lịch của mình hoặc khách mình phụ trách.
     */
    private function manages(User $user, Appointment $appointment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return $appointment->user_id === $user->id
                || $appointment->customer->assigned_to === $user->id;
        }

        return false;
    }
}
