<?php

namespace App\Repositories\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Repositories\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    /**
     * Phân trang khách hàng theo phạm vi của user (role) + filter.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Lấy chi tiết khách hàng kèm services, creator, assignee, appointments, activities.
     */
    public function findWithRelations(int $id): ?Customer;
}
