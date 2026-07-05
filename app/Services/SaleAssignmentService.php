<?php

namespace App\Services;

use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class SaleAssignmentService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private SettingService $settings,
    ) {}

    /**
     * Chọn sale kế tiếp theo round-robin. Trả null nếu không có sale active.
     */
    public function pickNextSale(): ?int
    {
        $ids = $this->users->activeSaleIds();

        if (empty($ids)) {
            Log::warning('SaleAssignmentService: không có sale active để gán khách', ['service' => 'tn-api']);

            return null;
        }

        return $this->settings->rotateCursor('last_assigned_sale_id', $ids);
    }
}
