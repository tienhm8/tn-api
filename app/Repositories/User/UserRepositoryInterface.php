<?php

namespace App\Repositories\User;

use App\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find an active user by email (fresh, không cache — phục vụ đăng nhập).
     */
    public function findByEmail(string $email): ?Model;

    /**
     * Danh sách id các sale đang active, sắp theo id (ổn định cho round-robin).
     *
     * @return array<int, int>
     */
    public function activeSaleIds(): array;

    /**
     * Danh sách user active theo role, sắp theo tên.
     *
     * @return Collection<int, Model>
     */
    public function activeUsersByRole(string $role): Collection;
}
