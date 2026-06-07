<?php

namespace App\Policies;

use App\Models\TreatmentPlan;
use App\Models\User;

class TreatmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('medical_record.view');
    }

    public function view(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasPermission('medical_record.view') && $user->clinic_id === $treatmentPlan->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('medical_record.update');
    }

    public function update(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasPermission('medical_record.update')
            && $user->clinic_id === $treatmentPlan->clinic_id;
    }

    public function delete(User $user, TreatmentPlan $treatmentPlan): bool
    {
        return $user->hasPermission('medical_record.delete')
            && $user->clinic_id === $treatmentPlan->clinic_id;
    }
}
