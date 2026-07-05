<?php

namespace App\Repositories\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Repositories\EloquentRepository;
use App\Traits\HasCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CustomerRepository extends EloquentRepository implements CustomerRepositoryInterface
{
    use HasCache;

    public function model(): string
    {
        return Customer::class;
    }

    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with(['services', 'assignee:id,name,email', 'creator:id,name,email']);

        $this->applyRoleScope($query, $user);
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function findWithRelations(int $id): ?Customer
    {
        return Customer::query()
            ->with([
                'services',
                'creator:id,name,email',
                'assignee:id,name,email',
                'appointments' => fn ($q) => $q->latest('scheduled_at')->with('user:id,name'),
                'activities' => fn ($q) => $q->latest()->with('user:id,name'),
            ])
            ->find($id);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    private function applyRoleScope(Builder $query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->hasRole('sale')) {
            $query->where('assigned_to', $user->id);

            return;
        }

        if ($user->hasRole('marketing')) {
            $query->where('created_by', $user->id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['service_id'])) {
            $query->whereHas('services', fn (Builder $q) => $q->where('services.id', $filters['service_id']));
        }

        if (! empty($filters['q'])) {
            $like = '%'.mb_strtolower((string) $filters['q']).'%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->whereRaw('LOWER(company_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(contact_name) LIKE ?', [$like]);
            });
        }
    }
}
