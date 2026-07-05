<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Get all records.
     *
     * @param  array<string>  $columns
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Find a record by ID.
     *
     * @param  array<string>  $columns
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by ID or fail.
     *
     * @param  array<string>  $columns
     */
    public function findOrFail(int $id, array $columns = ['*']): Model;

    /**
     * Find records by a field value.
     *
     * @param  array<string>  $columns
     */
    public function findByField(string $field, mixed $value, array $columns = ['*']): Collection;

    /**
     * Find records matching multiple conditions.
     *
     * @param  array<string, mixed>  $where
     * @param  array<string>  $columns
     */
    public function findWhere(array $where, array $columns = ['*']): Collection;

    /**
     * Find records where field is in given values.
     *
     * @param  array<mixed>  $values
     * @param  array<string>  $columns
     */
    public function findWhereIn(string $field, array $values, array $columns = ['*']): Collection;

    /**
     * Paginate records.
     *
     * @param  array<string>  $columns
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * Update a record by ID.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Model;

    /**
     * Update records matching conditions.
     *
     * @param  array<string, mixed>  $where
     * @param  array<string, mixed>  $attributes
     */
    public function updateWhere(array $where, array $attributes): int;

    /**
     * Delete a record by ID.
     */
    public function delete(int $id): bool;

    /**
     * Delete records matching conditions.
     *
     * @param  array<string, mixed>  $where
     */
    public function deleteWhere(array $where): int;

    /**
     * Get the first record matching current query.
     *
     * @param  array<string>  $columns
     */
    public function first(array $columns = ['*']): ?Model;

    /**
     * Count records matching conditions.
     *
     * @param  array<string, mixed>  $where
     */
    public function count(array $where = []): int;

    /**
     * Get a collection of column values.
     */
    public function pluck(string $column, ?string $key = null): \Illuminate\Support\Collection;

    /**
     * Eager load relations.
     *
     * @param  array<string>|string  $relations
     */
    public function with(array|string $relations): static;

    /**
     * Add subselect queries to count relations.
     *
     * @param  array<string>|string  $relations
     */
    public function withCount(array|string $relations): static;

    /**
     * Order by column.
     */
    public function orderBy(string $column, string $direction = 'asc'): static;

    /**
     * Add a basic where clause.
     */
    public function where(string $field, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a where-in clause.
     *
     * @param  array<mixed>  $values
     */
    public function whereIn(string $field, array $values): static;

    /**
     * Filter where field is not in given values.
     *
     * @param  array<mixed>  $values
     */
    public function whereNotIn(string $field, array $values): static;

    /**
     * Add a whereHas clause.
     */
    public function whereHas(string $relation, \Closure $closure): static;

    /**
     * Add a has clause.
     */
    public function has(string $relation): static;

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): static;

    /**
     * Skip the given number of results.
     */
    public function offset(int $offset): static;

    /**
     * First or create.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function firstOrCreate(array $attributes, array $values = []): Model;

    /**
     * Update or create.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;
}
