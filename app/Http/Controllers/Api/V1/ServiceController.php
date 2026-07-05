<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Services\ServiceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceService $serviceService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return ServiceResource::collection($this->serviceService->activeList());
    }
}
