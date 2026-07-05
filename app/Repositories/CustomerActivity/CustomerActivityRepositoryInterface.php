<?php

namespace App\Repositories\CustomerActivity;

use App\Enums\ActivityType;
use App\Models\CustomerActivity;
use App\Repositories\RepositoryInterface;

interface CustomerActivityRepositoryInterface extends RepositoryInterface
{
    /**
     * Ghi một bản ghi nhật ký chăm sóc cho khách hàng.
     */
    public function log(int $customerId, ?int $userId, ActivityType $type, string $content): CustomerActivity;
}
