<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\Service\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceService
{
    public function __construct(
        private ServiceRepositoryInterface $services,
    ) {}

    /**
     * @return Collection<int, Service>
     */
    public function activeList(): Collection
    {
        return $this->services->activeList();
    }
}
