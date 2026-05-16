<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('visit.view');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $user->hasPermission('visit.view') && $user->clinic_id === $visit->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('visit.create');
    }

    public function update(User $user, Visit $visit): bool
    {
        return $user->hasPermission('visit.update') && $user->clinic_id === $visit->clinic_id;
    }

    public function delete(User $user, Visit $visit): bool
    {
        return $user->hasPermission('visit.delete') && $user->clinic_id === $visit->clinic_id;
    }
}
