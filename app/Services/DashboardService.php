<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Appointment\AppointmentRepositoryInterface;

class DashboardService
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
    ) {}

    /**
     * Tổng hợp dashboard theo role người gọi.
     *
     * @return array<string, mixed>
     */
    public function summaryFor(User $user): array
    {
        $role = $this->primaryRole($user);
        $reminders = $this->reminderCounts($user);

        return [
            'role' => $role,
            'stats' => match ($role) {
                'admin' => $this->adminStats(),
                'marketing' => $this->marketingStats($user),
                'sale' => $this->saleStats($user, $reminders),
                default => [],
            },
            'reminders' => $reminders,
            'breakdown' => match ($role) {
                'admin' => ['by_status' => $this->byStatus(), 'top_services' => $this->topServices()],
                'marketing' => ['by_lead_source' => $this->byLeadSource($user)],
                default => [],
            },
        ];
    }

    private function primaryRole(User $user): string
    {
        foreach (['admin', 'sale', 'marketing'] as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return 'unknown';
    }

    /**
     * @return array{overdue: int, today: int, tomorrow: int}
     */
    private function reminderCounts(User $user): array
    {
        $buckets = $this->appointments->bucketsForUser($user);

        return [
            'overdue' => $buckets['overdue']->count(),
            'today' => $buckets['today']->count(),
            'tomorrow' => $buckets['tomorrow']->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminStats(): array
    {
        $total = Customer::count();
        $won = Customer::where('status', CustomerStatus::Won->value)->count();

        return [
            $this->stat('total', 'Tổng khách hàng', $total, 'ri-group-line', 'primary'),
            $this->stat('new_month', 'KH mới trong tháng', Customer::where('created_at', '>=', now()->startOfMonth())->count(), 'ri-user-add-line', 'info'),
            $this->stat('won', 'Đã chốt hợp đồng', $won, 'ri-checkbox-circle-line', 'success'),
            $this->stat('conversion', 'Tỷ lệ chuyển đổi', $total > 0 ? round($won * 100 / $total, 1).'%' : '0%', 'ri-line-chart-line', 'warning'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingStats(User $user): array
    {
        $total = Customer::where('created_by', $user->id)->count();
        $newToday = Customer::where('created_by', $user->id)->where('created_at', '>=', now()->startOfDay())->count();
        $transferred = Customer::where('created_by', $user->id)->whereNotNull('assigned_to')->count();

        return [
            $this->stat('total', 'Khách mình tạo', $total, 'ri-group-line', 'primary'),
            $this->stat('new_today', 'Mới hôm nay', $newToday, 'ri-user-add-line', 'info'),
            $this->stat('transferred', 'Đã chuyển sale', $transferred, 'ri-user-shared-line', 'success'),
        ];
    }

    /**
     * @param  array{overdue: int, today: int, tomorrow: int}  $reminders
     * @return array<int, array<string, mixed>>
     */
    private function saleStats(User $user, array $reminders): array
    {
        $assigned = Customer::where('assigned_to', $user->id)->count();
        $won = Customer::where('assigned_to', $user->id)->where('status', CustomerStatus::Won->value)->count();

        return [
            $this->stat('assigned', 'Khách được giao', $assigned, 'ri-user-3-line', 'primary'),
            $this->stat('call_today', 'Cần gọi hôm nay', $reminders['today'], 'ri-phone-line', 'warning'),
            $this->stat('overdue', 'Quá hạn chăm sóc', $reminders['overdue'], 'ri-alarm-warning-line', 'danger'),
            $this->stat('won', 'Đã chốt', $won, 'ri-checkbox-circle-line', 'success'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byStatus(): array
    {
        return Customer::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->map(fn (Customer $row): array => [
                'value' => $row->status->value,
                'label' => $row->status->label(),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byLeadSource(User $user): array
    {
        return Customer::query()
            ->where('created_by', $user->id)
            ->whereNotNull('lead_source')
            ->selectRaw('lead_source, count(*) as total')
            ->groupBy('lead_source')
            ->get()
            ->map(fn (Customer $row): array => [
                'value' => $row->lead_source?->value,
                'label' => $row->lead_source?->label(),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topServices(): array
    {
        return Service::query()
            ->withCount('customers')
            ->orderByDesc('customers_count')
            ->limit(5)
            ->get()
            ->map(fn (Service $service): array => [
                'name' => $service->name,
                'count' => (int) $service->customers_count,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function stat(string $key, string $label, int|string $value, string $icon, string $color): array
    {
        return compact('key', 'label', 'value', 'icon', 'color');
    }
}
