<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('department.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermission('department.view') && $user->clinic_id === $department->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('department.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission('department.update') && $user->clinic_id === $department->clinic_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermission('department.delete') && $user->clinic_id === $department->clinic_id;
    }
}
