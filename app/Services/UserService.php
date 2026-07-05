<?php

namespace App\Services;

use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @return Collection<int, Model>
     */
    public function activeUsersByRole(string $role): Collection
    {
        return $this->users->activeUsersByRole($role);
    }
}
