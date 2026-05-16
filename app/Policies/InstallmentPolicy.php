<?php

namespace App\Policies;

use App\Models\Installment;
use App\Models\User;

class InstallmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('installment.view');
    }

    public function view(User $user, Installment $installment): bool
    {
        return $user->hasPermission('installment.view') && $user->clinic_id === $installment->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('installment.create');
    }

    public function update(User $user, Installment $installment): bool
    {
        return $user->hasPermission('installment.update') && $user->clinic_id === $installment->clinic_id;
    }

    public function delete(User $user, Installment $installment): bool
    {
        return $user->hasPermission('installment.delete') && $user->clinic_id === $installment->clinic_id;
    }
}
