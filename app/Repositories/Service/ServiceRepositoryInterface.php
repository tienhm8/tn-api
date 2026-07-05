<?php

namespace App\Repositories\Service;

use App\Models\Service;
use App\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface extends RepositoryInterface
{
    /**
     * Danh mục dịch vụ đang active (có cache), sắp theo sort_order.
     *
     * @return Collection<int, Service>
     */
    public function activeList(): Collection;
}
