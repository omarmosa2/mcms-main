<?php

namespace App\Observers;

use App\Models\Department;
use App\Services\Cache\CacheService;

class DepartmentObserver
{
    public function __construct(private CacheService $cacheService) {}

    public function saved(Department $department): void
    {
        $this->cacheService->invalidateClinicDepartments($department->clinic_id);
    }

    public function deleted(Department $department): void
    {
        $this->cacheService->invalidateClinicDepartments($department->clinic_id);
    }
}
