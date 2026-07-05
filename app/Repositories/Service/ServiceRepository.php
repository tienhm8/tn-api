<?php

namespace App\Repositories\Service;

use App\Models\Service;
use App\Repositories\EloquentRepository;
use App\Traits\HasCache;
use Illuminate\Database\Eloquent\Collection;

class ServiceRepository extends EloquentRepository implements ServiceRepositoryInterface
{
    use HasCache;

    public function model(): string
    {
        return Service::class;
    }

    public function activeList(): Collection
    {
        return $this->getCache('active_list', function () {
            return Service::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });
    }
}
