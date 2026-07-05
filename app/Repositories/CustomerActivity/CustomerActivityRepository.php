<?php

namespace App\Repositories\CustomerActivity;

use App\Enums\ActivityType;
use App\Models\CustomerActivity;
use App\Repositories\EloquentRepository;

class CustomerActivityRepository extends EloquentRepository implements CustomerActivityRepositoryInterface
{
    public function model(): string
    {
        return CustomerActivity::class;
    }

    public function log(int $customerId, ?int $userId, ActivityType $type, string $content): CustomerActivity
    {
        return CustomerActivity::create([
            'customer_id' => $customerId,
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
        ]);
    }
}
