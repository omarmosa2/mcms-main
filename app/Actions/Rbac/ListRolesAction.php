<?php

namespace App\Actions\Rbac;

use App\Actions\BaseAction;
use App\Services\Cache\CacheService;
use Illuminate\Database\Eloquent\Collection;

class ListRolesAction extends BaseAction
{
    public function __construct(private CacheService $cacheService) {}

    public function handle(int $clinicId): Collection
    {
        return $this->cacheService->getClinicRoles($clinicId);
    }
}
