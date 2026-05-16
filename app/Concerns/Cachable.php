<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

trait Cachable
{
    protected static function bootCachable(): void
    {
        static::created(fn ($model) => $model->flushModelCache());
        static::updated(fn ($model) => $model->flushModelCache());
        static::deleted(fn ($model) => $model->flushModelCache());
    }

    public function cacheKey(string $suffix = ''): string
    {
        $prefix = property_exists($this, 'cachePrefix')
            ? $this->cachePrefix
            : str($this->getTable())->slug()->value();

        $key = "{$prefix}:{$this->getKey()}";

        return $suffix !== '' ? "{$key}:{$suffix}" : $key;
    }

    public function clinicCacheKey(string $suffix = ''): string
    {
        $prefix = property_exists($this, 'cachePrefix')
            ? $this->cachePrefix
            : str($this->getTable())->slug()->value();

        $clinicId = $this->clinic_id ?? 'global';

        return "clinic:{$clinicId}:{$prefix}".($suffix !== '' ? ":{$suffix}" : '');
    }

    public function invalidateCache(): void
    {
        $this->flushModelCache();
    }

    public function flushModelCache(): void
    {
        if (property_exists($this, 'cacheKeys')) {
            foreach ($this->cacheKeys as $key) {
                Cache::forget($key);
            }
        }
    }

    public static function forgetCacheForClinic(int $clinicId, string $prefix = ''): void
    {
        $table = (new static)->getTable();
        $prefix = $prefix ?: str($table)->slug()->value();

        Cache::forget("clinic:{$clinicId}:{$prefix}");
        Cache::forget("clinic:{$clinicId}:{$prefix}:list");
        Cache::forget("clinic:{$clinicId}:{$prefix}:count");
    }

    public static function clearClinicCache(int $clinicId): void
    {
        Cache::forget("clinic:{$clinicId}:security_policy");
        Cache::forget("clinic:{$clinicId}:roles");
        Cache::forget("clinic:{$clinicId}:permissions");
        Cache::forget("clinic:{$clinicId}:departments");
        Cache::forget("clinic:{$clinicId}:expense_categories");
        Cache::forget("clinic:{$clinicId}:dashboard_stats");
    }
}
