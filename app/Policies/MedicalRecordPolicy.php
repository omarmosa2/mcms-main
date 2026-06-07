<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('medical_record.view');
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->hasPermission('medical_record.view') && $user->clinic_id === $medicalRecord->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('medical_record.create');
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->hasPermission('medical_record.update')
            && $user->clinic_id === $medicalRecord->clinic_id;
    }

    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->hasPermission('medical_record.delete')
            && $user->clinic_id === $medicalRecord->clinic_id;
    }
}
