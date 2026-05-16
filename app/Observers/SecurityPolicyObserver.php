<?php

namespace App\Observers;

use App\Models\SecurityPolicy;
use App\Services\Cache\CacheService;

class SecurityPolicyObserver
{
    public function __construct(private CacheService $cacheService) {}

    public function saved(SecurityPolicy $policy): void
    {
        $this->cacheService->invalidateSecurityPolicy($policy->clinic_id);
    }

    public function deleted(SecurityPolicy $policy): void
    {
        $this->cacheService->invalidateSecurityPolicy($policy->clinic_id);
    }
}
