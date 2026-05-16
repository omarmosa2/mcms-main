<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateUserAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AssignUserRoleAction $assignUserRoleAction,
    ) {}

    public function handle(
        int $clinicId,
        int $userId,
        array $payload,
    ): User {
        $email = strtolower(trim((string) $payload['email']));
        $roleName = (string) $payload['role_name'];

        $existingUser = User::query()
            ->where('clinic_id', $clinicId)
            ->where('email', $email)
            ->exists();

        if ($existingUser) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists in this clinic.',
            ]);
        }

        $role = Role::query()
            ->where('clinic_id', $clinicId)
            ->where('name', $roleName)
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role_name' => 'The selected role is not available for this clinic.',
            ]);
        }

        $user = User::query()->create([
            'clinic_id' => $clinicId,
            'name' => trim((string) $payload['name']),
            'email' => $email,
            'password' => Hash::make($payload['password'] ?? bin2hex(random_bytes(16))),
            'is_active' => $payload['is_active'] ?? true,
            'is_super_admin' => false,
        ]);

        $this->assignUserRoleAction->handle(
            $user,
            $roleName,
            $userId,
        );

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'users.create',
            auditable: $user,
            metadata: [
                'user_id' => $user->id,
                'email' => $email,
                'role_name' => $roleName,
            ],
        );

        return $user;
    }
}
