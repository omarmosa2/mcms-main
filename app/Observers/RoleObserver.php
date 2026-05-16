<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\Cache\CacheService;

class RoleObserver
{
    public function __construct(private CacheService $cacheService) {}

    public function saved(Role $role): void
    {
        $this->cacheService->invalidateClinicRoles($role->clinic_id);
        $this->cacheService->invalidateAllUserPermissions($role->clinic_id);
    }

    public function deleted(Role $role): void
    {
        $this->cacheService->invalidateClinicRoles($role->clinic_id);
        $this->cacheService->invalidateAllUserPermissions($role->clinic_id);
    }
}
