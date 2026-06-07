<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;

class FollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('medical_record.view');
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        return $user->hasPermission('medical_record.view') && $user->clinic_id === $followUp->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('medical_record.update');
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return $user->hasPermission('medical_record.update')
            && $user->clinic_id === $followUp->clinic_id;
    }

    public function delete(User $user, FollowUp $followUp): bool
    {
        return $user->hasPermission('medical_record.delete')
            && $user->clinic_id === $followUp->clinic_id;
    }
}
