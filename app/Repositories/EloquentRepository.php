<?php

namespace App\Repositories;

use Illuminate\Container\Container as Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class EloquentRepository implements RepositoryInterface
{
    protected Model|Builder $model;

    public function __construct(
        protected Application $app,
    ) {
        $this->makeModel();
    }

    /**
     * Specify the Model class name.
     */
    abstract public function model(): string;

    /**
     * Resolve and set the model instance.
     */
    public function makeModel(): Model
    {
        $model = $this->app->make($this->model());

        if (! $model instanceof Model) {
            throw new \RuntimeException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Reset model to a fresh instance (clears any query state).
     */
    public function resetModel(): void
    {
        $this->makeModel();
    }

    /**
     * Get the underlying query builder.
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    // =========================================================================
    // READ
    // =========================================================================

    public function all(array $columns = ['*']): Collection
    {
        $results = $this->model instanceof Builder
            ? $this->model->get($columns)
            : $this->model->all($columns);

        $this->resetModel();

        return $results;
    }

    public function find(int $id, array $columns = ['*']): ?Model
    {
        $result = $this->model->find($id, $columns);
        $this->resetModel();

        return $result;
    }

    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        $result = $this->model->findOrFail($id, $columns);
        $this->resetModel();

        return $result;
    }

    public function findByField(string $field, mixed $value, array $columns = ['*']): Collection
    {
        $results = $this->model->where($field, '=', $value)->get($columns);
        $this->resetModel();

        return $results;
    }

    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        $this->applyConditions($where);
        $results = $this->model->get($columns);
        $this->resetModel();

        return $results;
    }

    public function findWhereIn(string $field, array $values, array $columns = ['*']): Collection
    {
        $results = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

        return $results;
    }

    public function first(array $columns = ['*']): ?Model
    {
        $result = $this->model->first($columns);
        $this->resetModel();

        return $result;
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $results = $this->model->paginate($perPage, $columns);
        $results->appends(app('request')->query());
        $this->resetModel();

        return $results;
    }

    public function count(array $where = []): int
    {
        if ($where) {
            $this->applyConditions($where);
        }

        $result = $this->model->count();
        $this->resetModel();

        return $result;
    }

    public function pluck(string $column, ?string $key = null): \Illuminate\Support\Collection
    {
        $results = $this->model->pluck($column, $key);
        $this->resetModel();

        return $results;
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance($attributes);
        $model->save();
        $this->resetModel();

        return $model;
    }

    public function update(int $id, array $attributes): Model
    {
        $model = $this->model->findOrFail($id);
        $model->fill($attributes);
        $model->save();
        $this->resetModel();

        return $model;
    }

    public function updateWhere(array $where, array $attributes): int
    {
        $this->applyConditions($where);
        $updated = $this->model->update($attributes);
        $this->resetModel();

        return $updated;
    }

    public function delete(int $id): bool
    {
        $model = $this->model->findOrFail($id);
        $this->resetModel();

        return (bool) $model->delete();
    }

    public function deleteWhere(array $where): int
    {
        $this->applyConditions($where);
        $deleted = $this->model->delete();
        $this->resetModel();

        return $deleted;
    }

    public function firstOrCreate(array $attributes, array $values = []): Model
    {
        $model = $this->model->firstOrCreate($attributes, $values);
        $this->resetModel();

        return $model;
    }

    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $model = $this->model->updateOrCreate($attributes, $values);
        $this->resetModel();

        return $model;
    }

    // =========================================================================
    // QUERY BUILDER (chainable, return $this)
    // =========================================================================

    public function with(array|string $relations): static
    {
        $this->model = $this->model->with($relations);

        return $this;
    }

    public function withCount(array|string $relations): static
    {
        $this->model = $this->model->withCount($relations);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    public function where(string $field, mixed $operator = null, mixed $value = null): static
    {
        $this->model = $this->model->where($field, $operator, $value);

        return $this;
    }

    public function whereIn(string $field, array $values): static
    {
        $this->model = $this->model->whereIn($field, $values);

        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $this->model = $this->model->whereNotIn($field, $values);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->model = $this->model->limit($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->model = $this->model->offset($offset);

        return $this;
    }

    public function has(string $relation): static
    {
        $this->model = $this->model->has($relation);

        return $this;
    }

    public function whereHas(string $relation, \Closure $closure): static
    {
        $this->model = $this->model->whereHas($relation, $closure);

        return $this;
    }

    // =========================================================================
    // INTERNAL
    // =========================================================================

    /**
     * Apply an array of where conditions to the model.
     *
     * Supports:
     *   ['field' => 'value'] → where field = value
     *   ['field' => ['field', 'operator', 'value']] → where field operator value
     *
     * @param  array<string, mixed>  $where
     */
    protected function applyConditions(array $where): void
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                [$f, $op, $v] = $value;
                $this->model = $this->model->where($f, $op, $v);
            } else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }
}
