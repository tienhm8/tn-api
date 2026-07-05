<?php

use App\Repositories\Setting\SettingRepositoryInterface;
use Random\RandomException;

if (! function_exists('randomizeTtl')) {
    /**
     * Add random jitter to a TTL value to prevent cache stampede.
     *
     * Applies ±15% jitter by default, ensuring distributed cache expiration
     * across multiple keys/requests.
     *
     * @param  int  $ttl  Base TTL in seconds
     * @param  float  $jitterPercent  Jitter range as decimal (0.15 = ±15%)
     * @return int Randomized TTL in seconds (minimum 1)
     *
     * @throws RandomException
     */
    function randomizeTtl(int $ttl, float $jitterPercent = 0.15): int
    {
        $jitter = (int) ceil($ttl * $jitterPercent);

        return max(1, random_int($ttl - $jitter, $ttl + $jitter));
    }
}

if (! function_exists('buildCacheKey')) {
    /**
     * Build a prefixed cache key for a given entity and identifier.
     *
     * @param  string  $entity  Entity name (e.g., 'users', 'products')
     * @param  string  $identifier  Unique cache identifier
     * @return string Formatted cache key (e.g., 'users:active_list')
     */
    function buildCacheKey(string $entity, string $identifier): string
    {
        return $entity.':'.$identifier;
    }
}

if (! function_exists('setting')) {
    /**
     * Đọc một cấu hình hệ thống theo key (có cache qua SettingRepository).
     *
     * @param  string  $key  Setting key (vd `reminder_lead_minutes`)
     * @param  mixed  $default  Giá trị mặc định nếu chưa cấu hình
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingRepositoryInterface::class)->get($key, $default);
    }
}
