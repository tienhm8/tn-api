<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\EloquentRepository;
use App\Traits\HasCache;
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
}
