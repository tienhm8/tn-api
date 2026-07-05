<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\EloquentRepository;
use App\Traits\HasCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends EloquentRepository implements UserRepositoryInterface
{
    use HasCache;

    public function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?Model
    {
        return $this->findByField('email', $email)->first();
    }

    public function activeSaleIds(): array
    {
        return User::role('sale')
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public function activeUsersByRole(string $role): Collection
    {
        return User::role($role)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
