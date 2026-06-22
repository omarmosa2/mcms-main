<?php

namespace App\Policies;

use App\Models\DoctorProfile;
use App\Models\User;

class DoctorProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('doctor.view');
    }

    public function view(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasPermission('doctor.view') && $user->clinic_id === $doctorProfile->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('doctor.create');
    }

    public function update(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasPermission('doctor.update') && $user->clinic_id === $doctorProfile->clinic_id;
    }

    public function delete(User $user, DoctorProfile $doctorProfile): bool
    {
        return $user->hasPermission('doctor.delete') && $user->clinic_id === $doctorProfile->clinic_id;
    }
}
