<?php

namespace App\Repositories\Setting;

use App\Models\Setting;
use App\Repositories\EloquentRepository;
use App\Traits\HasCache;

class SettingRepository extends EloquentRepository implements SettingRepositoryInterface
{
    use HasCache;

    public function model(): string
    {
        return Setting::class;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->getCache("key:{$key}", function () use ($key) {
            return $this->findByField('key', $key)->first()?->value;
        });

        return $value ?? $default;
    }

    public function put(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->forgetKey($key);
    }

    public function forgetKey(string $key): void
    {
        $this->forgetCache("key:{$key}");
    }
}
