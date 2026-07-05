<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Random\RandomException;

trait HasCache
{
    /**
     * Sentinel key trong cached array để phân biệt frozen Model/Collection
     * với array user thường. Rất hiếm va chạm vì key có space + prefix `__`.
     */
    private const string CACHE_TYPE_KEY = '__hasCacheType';

    /**
     * Freeze Model/Collection thành plain array trước khi cache.
     *
     * Lý do: Laravel `cache.serializable_classes` mặc định `false` (Laravel 11+) — chặn
     * unserialize mọi class object để phòng gadget chain attack khi APP_KEY leak. Cache
     * Model trực tiếp → hit trả `__PHP_Incomplete_Class` → TypeError.
     *
     * Freeze recursively handle relations để `->with(...)` cũng được cache đúng.
     */
    protected function freezeForCache(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [
                self::CACHE_TYPE_KEY => 'model',
                'class' => get_class($value),
                'attributes' => $value->getAttributes(),
                'exists' => $value->exists,
                'relations' => array_map(
                    fn ($rel) => $this->freezeForCache($rel),
                    $value->getRelations()
                ),
            ];
        }

        if ($value instanceof EloquentCollection || $value instanceof SupportCollection) {
            return [
                self::CACHE_TYPE_KEY => 'collection',
                'eloquent' => $value instanceof EloquentCollection,
                'items' => $value->map(fn ($item) => $this->freezeForCache($item))->all(),
            ];
        }

        return $value;
    }

    /**
     * Thaw frozen cache payload → Model/Collection/scalar như ban đầu.
     */
    protected function thawFromCache(mixed $value): mixed
    {
        if (! is_array($value) || ! isset($value[self::CACHE_TYPE_KEY])) {
            return $value;
        }

        if ($value[self::CACHE_TYPE_KEY] === 'model') {
            /** @var class-string<Model> $class */
            $class = $value['class'];
            $model = (new $class)->newFromBuilder($value['attributes'] ?? []);
            $model->exists = (bool) ($value['exists'] ?? true);
            foreach ($value['relations'] ?? [] as $name => $frozenRel) {
                $model->setRelation($name, $this->thawFromCache($frozenRel));
            }

            return $model;
        }

        if ($value[self::CACHE_TYPE_KEY] === 'collection') {
            $items = array_map(fn ($item) => $this->thawFromCache($item), $value['items'] ?? []);

            return $value['eloquent'] ?? false
                ? new EloquentCollection($items)
                : new SupportCollection($items);
        }

        return $value;
    }

    /**
     * Wrap callback — freeze kết quả ngay để store an toàn.
     */
    private function freezingCallback(Closure $callback): Closure
    {
        return fn () => $this->freezeForCache($callback());
    }

    /**
     * Default cache TTL in seconds (5 minutes).
     */
    protected int $cacheTtl = 300;

    /**
     * Stale-while-revalidate window in seconds (10 minutes).
     *
     * Used as the upper bound for Cache::flexible().
     */
    protected int $cacheStaleSeconds = 600;

    /**
     * Get the cache entity prefix derived from the model table name.
     */
    protected function getCachePrefix(): string
    {
        return $this->makeModel()->getTable();
    }

    /**
     * Build a full cache key with entity prefix.
     */
    protected function getCacheKey(string $identifier): string
    {
        return buildCacheKey($this->getCachePrefix(), $identifier);
    }

    /**
     * Retrieve a value from two-tier cache (memo + store) using Cache::remember.
     *
     * L1: Cache::memo() — in-memory, zero network cost within same request.
     * L2: Configured cache store (redis recommended) — shared cross-request.
     *
     * @param  string  $identifier  Cache key identifier (auto-prefixed with entity)
     * @param  Closure  $callback  Data retrieval callback on cache miss
     * @param  int|null  $ttl  TTL in seconds (null = use default, randomized with jitter)
     *
     * @throws RandomException
     */
    public function getCache(string $identifier, Closure $callback, ?int $ttl = null): mixed
    {
        $key = $this->getCacheKey($identifier);

        try {
            $ttl = randomizeTtl($ttl ?? $this->cacheTtl);
            $frozen = Cache::memo()->remember($key, $ttl, $this->freezingCallback($callback));

            return $this->thawFromCache($frozen);
        } catch (\Throwable $e) {
            Log::error(__CLASS__.':'.__FUNCTION__.': Cache get failed', ['service' => 'tn-api', 'class' => __CLASS__, 'action' => __FUNCTION__, 'subject' => $key, 'error_code' => class_basename($e), 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Retrieve a value using the stale-while-revalidate pattern.
     *
     * Serves stale data immediately while refreshing in the background
     * via a deferred function. Ideal for high-traffic endpoints.
     *
     * L1: Cache::memo() — in-memory within the same request.
     * L2: Cache::flexible() — SWR pattern on the configured store.
     *
     * @param  string  $identifier  Cache key identifier (auto-prefixed with entity)
     * @param  Closure  $callback  Data retrieval callback on cache miss/stale
     * @param  array{0: int, 1: int}|null  $ttl  [freshSeconds, staleSeconds] (null = use defaults, randomized)
     *
     * @throws RandomException
     */
    public function getFlexibleCache(string $identifier, Closure $callback, ?array $ttl = null): mixed
    {
        $key = $this->getCacheKey($identifier);

        try {
            $freshSeconds = randomizeTtl($ttl[0] ?? $this->cacheTtl);
            $staleSeconds = randomizeTtl($ttl[1] ?? $this->cacheStaleSeconds);

            $frozen = Cache::memo()->flexible($key, [$freshSeconds, $staleSeconds], $this->freezingCallback($callback));

            return $this->thawFromCache($frozen);
        } catch (\Throwable $e) {
            Log::error(__CLASS__.':'.__FUNCTION__.': Cache flexible get failed', ['service' => 'tn-api', 'class' => __CLASS__, 'action' => __FUNCTION__, 'subject' => $key, 'error_code' => class_basename($e), 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Force to refresh a cache key by forgetting and re-caching.
     *
     * @param  string  $identifier  Cache key identifier (auto-prefixed with entity)
     * @param  Closure  $callback  Data retrieval callback
     * @param  int|null  $ttl  TTL in seconds (null = use default, randomized with jitter)
     *
     * @throws RandomException
     */
    public function refreshCache(string $identifier, Closure $callback, ?int $ttl = null): mixed
    {
        $key = $this->getCacheKey($identifier);

        try {
            Cache::forget($key);

            $ttl = randomizeTtl($ttl ?? $this->cacheTtl);
            $frozen = Cache::memo()->remember($key, $ttl, $this->freezingCallback($callback));

            return $this->thawFromCache($frozen);
        } catch (\Throwable $e) {
            Log::error(__CLASS__.':'.__FUNCTION__.': Cache refresh failed', ['service' => 'tn-api', 'class' => __CLASS__, 'action' => __FUNCTION__, 'subject' => $key, 'error_code' => class_basename($e), 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Remove a cached value by key.
     *
     * @param  string  $identifier  Cache key identifier (auto-prefixed with entity)
     */
    public function forgetCache(string $identifier): bool
    {
        $key = $this->getCacheKey($identifier);

        try {
            return Cache::forget($key);
        } catch (\Throwable $e) {
            Log::error(__CLASS__.':'.__FUNCTION__.': Cache forget failed', ['service' => 'tn-api', 'class' => __CLASS__, 'action' => __FUNCTION__, 'subject' => $key, 'error_code' => class_basename($e), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Flush all cache entries cho entity này bằng Redis SCAN + DEL theo pattern key.
     *
     * Lý do không dùng `Cache::tags([...])->flush()`: `getCache` put không có tags nên
     * tag flush không match. SCAN pattern = redis_prefix + cache_prefix + entity:* — quét
     * cursor-based, không block, DEL theo batch.
     *
     * Chỉ hoạt động trên Redis store. Store khác → log warning + return false.
     */
    public function flushEntityCache(): bool
    {
        try {
            if (config('cache.default') !== 'redis') {
                return false;
            }

            // Laravel Cache::put thêm `cache.prefix` trước key. Dùng Cache::forget để clear —
            // Cache layer tự quản prefix này. Chỉ cần Redis SCAN raw + strip Cache prefix trước
            // khi gọi forget.
            $redisPrefix = config('database.redis.options.prefix', '');
            $cachePrefix = config('cache.prefix', '');
            $scanPattern = $redisPrefix.$cachePrefix.$this->getCachePrefix().':*';

            $connection = Redis::connection(
                config('cache.stores.redis.connection', 'cache')
            );
            $redis = $connection->client();

            $cursor = null;
            do {
                $result = $redis->scan($cursor, $scanPattern, 200);
                if ($result === false) {
                    break;
                }
                foreach ($result as $fullKey) {
                    // $fullKey = "<redis_prefix><cache_prefix><entity>:..." → strip cả 2 prefix
                    // để có key Laravel Cache expect.
                    $cacheKey = substr($fullKey, strlen($redisPrefix.$cachePrefix));
                    Cache::forget($cacheKey);
                }
            } while ($cursor > 0);

            return true;
        } catch (\Throwable $e) {
            Log::error(__CLASS__.':'.__FUNCTION__.': Cache flush entity failed', ['service' => 'tn-api', 'class' => __CLASS__, 'action' => __FUNCTION__, 'subject' => $this->getCachePrefix(), 'error_code' => class_basename($e), 'error' => $e->getMessage()]);

            return false;
        }
    }
}
