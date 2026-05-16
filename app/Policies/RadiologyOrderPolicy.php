<?php

namespace App\Policies;

use App\Models\RadiologyOrder;
use App\Models\User;

class RadiologyOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('radiology.order.view');
    }

    public function view(User $user, RadiologyOrder $radiologyOrder): bool
    {
        return $user->hasPermission('radiology.order.view') && $user->clinic_id === $radiologyOrder->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('radiology.order.create');
    }

    public function update(User $user, RadiologyOrder $radiologyOrder): bool
    {
        return $user->hasPermission('radiology.order.update') && $user->clinic_id === $radiologyOrder->clinic_id;
    }

    public function delete(User $user, RadiologyOrder $radiologyOrder): bool
    {
        return $user->hasPermission('radiology.order.delete') && $user->clinic_id === $radiologyOrder->clinic_id;
    }
}
