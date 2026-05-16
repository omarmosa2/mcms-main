<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('patient.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patient.view') && $user->clinic_id === $patient->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('patient.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patient.update') && $user->clinic_id === $patient->clinic_id;
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patient.delete') && $user->clinic_id === $patient->clinic_id;
    }
}
