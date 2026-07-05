<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'marketing']);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin');
    }

    public function reassign(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin');
    }

    public function changeStatus(User $user, Customer $customer): bool
    {
        return $this->owns($user, $customer);
    }

    /**
     * admin → tất cả; sale → khách được gán; marketing → khách mình tạo.
     */
    private function owns(User $user, Customer $customer): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return $customer->assigned_to === $user->id;
        }

        if ($user->hasRole('marketing')) {
            return $customer->created_by === $user->id;
        }

        return false;
    }
}
