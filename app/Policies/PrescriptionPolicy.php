<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('prescription.view');
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $user->hasPermission('prescription.view') && $user->clinic_id === $prescription->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('prescription.create');
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $user->hasPermission('prescription.update') && $user->clinic_id === $prescription->clinic_id;
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->hasPermission('prescription.delete') && $user->clinic_id === $prescription->clinic_id;
    }
}
