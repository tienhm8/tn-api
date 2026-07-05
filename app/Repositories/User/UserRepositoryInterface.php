<?php

namespace App\Repositories\User;

use App\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find an active user by email (fresh, không cache — phục vụ đăng nhập).
     */
    public function findByEmail(string $email): ?Model;
}
