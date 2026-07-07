<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\LostReason;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use App\Notifications\CustomerAssignedNotification;
use App\Repositories\Customer\CustomerRepositoryInterface;
use App\Repositories\CustomerActivity\CustomerActivityRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
        private CustomerActivityRepositoryInterface $activities,
        private SaleAssignmentService $saleAssignment,
        private SettingService $settings,
    ) {}

    /**
     * Tạo khách hàng + tự round-robin gán sale ngay.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $creator, CustomerSource $source = CustomerSource::Manual): Customer
    {
        $serviceIds = $data['service_ids'] ?? [];
        unset($data['service_ids']);

        /** @var array{0: Customer, 1: int|null} $result */
        $result = DB::transaction(function () use ($data, $creator, $serviceIds, $source): array {
            $code = $this->settings->nextCustomerCode();
            $saleId = $this->saleAssignment->pickNextSale();

            $customer = $this->customers->create(array_merge($data, [
                'code' => $code,
                'created_by' => $creator->id,
                'source' => $source->value,
                'assigned_to' => $saleId,
                'assigned_at' => $saleId ? now() : null,
                'status' => $saleId ? CustomerStatus::Assigned->value : CustomerStatus::New->value,
            ]));

            if (! empty($serviceIds)) {
                $customer->services()->sync($serviceIds);
            }

            $isImport = $source === CustomerSource::Import;
            $this->activities->log(
                $customer->id,
                $creator->id,
                $isImport ? ActivityType::Imported : ActivityType::Created,
                ($isImport ? 'Import khách hàng: ' : 'Tạo khách hàng: ').$customer->company_name
            );

            if ($saleId) {
                $this->activities->log($customer->id, $creator->id, ActivityType::Assigned, 'Tự động giao cho sale #'.$saleId.' (round-robin)');
            }

            return [$customer, $saleId];
        });

        [$customer, $assignedSaleId] = $result;

        if ($assignedSaleId) {
            $this->notifyAssignedSale($assignedSaleId, $customer);
        }

        return $customer->fresh(['services', 'assignee', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $serviceIds = $data['service_ids'] ?? null;
        unset($data['service_ids']);

        return DB::transaction(function () use ($customer, $data, $serviceIds): Customer {
            $customer->fill($data)->save();

            if ($serviceIds !== null) {
                $customer->services()->sync($serviceIds);
            }

            return $customer->fresh(['services', 'assignee', 'creator']);
        });
    }

    /**
     * Admin gán lại khách cho một sale khác.
     */
    public function reassign(Customer $customer, int $saleId, User $actor): Customer
    {
        DB::transaction(function () use ($customer, $saleId, $actor): void {
            $customer->assigned_to = $saleId;
            $customer->assigned_at = now();
            if ($customer->status === CustomerStatus::New) {
                $customer->status = CustomerStatus::Assigned;
            }
            $customer->save();

            $this->activities->log($customer->id, $actor->id, ActivityType::Assigned, 'Gán lại cho sale #'.$saleId);
        });

        $this->notifyAssignedSale($saleId, $customer);

        return $customer->fresh(['services', 'assignee', 'creator']);
    }

    public function changeStatus(Customer $customer, CustomerStatus $status, ?LostReason $lostReason, User $actor): Customer
    {
        DB::transaction(function () use ($customer, $status, $lostReason, $actor): void {
            $customer->status = $status;
            $customer->lost_reason = $status === CustomerStatus::Lost ? $lostReason : null;
            $customer->save();

            $this->activities->log($customer->id, $actor->id, ActivityType::StatusChange, 'Đổi trạng thái: '.$status->label());
        });

        return $customer->fresh(['services', 'assignee', 'creator']);
    }

    /**
     * Ghi một ghi chú chăm sóc vào timeline khách hàng.
     */
    public function addNote(Customer $customer, string $content, User $actor): CustomerActivity
    {
        return $this->activities
            ->log($customer->id, $actor->id, ActivityType::Note, $content)
            ->load('user:id,name');
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    private function notifyAssignedSale(int $saleId, Customer $customer): void
    {
        User::find($saleId)?->notify(new CustomerAssignedNotification($customer));
    }
}
