<?php

namespace App\Policies;

use App\Models\LabOrder;
use App\Models\User;

class LabOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lab.order.view');
    }

    public function view(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('lab.order.view') && $user->clinic_id === $labOrder->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('lab.order.create');
    }

    public function update(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('lab.order.update') && $user->clinic_id === $labOrder->clinic_id;
    }

    public function delete(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('lab.order.delete') && $user->clinic_id === $labOrder->clinic_id;
    }
}
