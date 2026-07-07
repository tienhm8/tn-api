<?php

namespace App\Repositories\Appointment;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Repositories\EloquentRepository;
use Illuminate\Database\Eloquent\Builder;

class AppointmentRepository extends EloquentRepository implements AppointmentRepositoryInterface
{
    public function model(): string
    {
        return Appointment::class;
    }

    public function bucketsForUser(User $user): array
    {
        $todayStart = now()->startOfDay();
        $tomorrowStart = now()->startOfDay()->addDay();
        $dayAfterStart = now()->startOfDay()->addDays(2);

        return [
            'overdue' => $this->scheduledFor($user)
                ->where('scheduled_at', '<', $todayStart)->get(),
            'today' => $this->scheduledFor($user)
                ->where('scheduled_at', '>=', $todayStart)
                ->where('scheduled_at', '<', $tomorrowStart)->get(),
            'tomorrow' => $this->scheduledFor($user)
                ->where('scheduled_at', '>=', $tomorrowStart)
                ->where('scheduled_at', '<', $dayAfterStart)->get(),
            'upcoming' => $this->scheduledFor($user)
                ->where('scheduled_at', '>=', $dayAfterStart)->get(),
        ];
    }

    public function minScheduledAt(int $customerId): ?string
    {
        return Appointment::query()
            ->where('customer_id', $customerId)
            ->where('status', AppointmentStatus::Scheduled->value)
            ->min('scheduled_at');
    }

    /**
     * Query cơ bản: lịch đang chờ, kèm customer + sale, đã scope theo role.
     *
     * @return Builder<Appointment>
     */
    private function scheduledFor(User $user): Builder
    {
        $query = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->with(['customer:id,code,company_name,phone,status', 'user:id,name'])
            ->orderBy('scheduled_at');

        $this->applyRoleScope($query, $user);

        return $query;
    }

    /**
     * admin → tất cả; sale → lịch của mình hoặc khách mình phụ trách; marketing → lịch của khách mình tạo.
     *
     * @param  Builder<Appointment>  $query
     */
    private function applyRoleScope(Builder $query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('sale')) {
            $query->where(function (Builder $sub) use ($user): void {
                $sub->where('user_id', $user->id)
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('assigned_to', $user->id));
            });

            return;
        }

        if ($user->hasRole('marketing')) {
            $query->whereHas('customer', fn (Builder $c) => $c->where('created_by', $user->id));

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
