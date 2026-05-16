<?php

namespace App\Actions\Rbac;

use App\Actions\BaseAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignUserRoleAction extends BaseAction
{
    public function handle(User $user, string $roleName, ?int $assignedBy = null): Role
    {
        $clinicId = $user->clinic_id;

        if ($clinicId === null) {
            throw new ModelNotFoundException('Cannot assign roles to a user without clinic context.');
        }

        $role = Role::query()
            ->forClinic($clinicId)
            ->where('name', $roleName)
            ->firstOrFail();

        $user->assignRole($role, $assignedBy);

        return $role;
    }
}
