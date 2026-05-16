<?php

namespace App\Observers;

use App\Models\Permission;
use App\Services\Cache\CacheService;

class PermissionObserver
{
    public function __construct(private CacheService $cacheService) {}

    public function saved(Permission $permission): void
    {
        $this->cacheService->invalidateAllUserPermissions($permission->clinic_id);
    }

    public function deleted(Permission $permission): void
    {
        $this->cacheService->invalidateAllUserPermissions($permission->clinic_id);
    }
}
