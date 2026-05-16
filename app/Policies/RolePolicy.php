<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('role.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('role.view') && $user->clinic_id === $role->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('role.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('role.update') && $user->clinic_id === $role->clinic_id;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('role.delete') && $user->clinic_id === $role->clinic_id;
    }
}
