<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\Rbac\AssignUserRoleAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateUserAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AssignUserRoleAction $assignUserRoleAction,
    ) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $targetUserId,
        array $payload,
    ): User {
        $user = User::query()
            ->forClinic($clinicId)
            ->where('id', $targetUserId)
            ->firstOrFail();

        if (isset($payload['email'])) {
            $email = strtolower(trim((string) $payload['email']));

            $existingUser = User::query()
                ->forClinic($clinicId)
                ->where('email', $email)
                ->where('id', '!=', $targetUserId)
                ->exists();

            if ($existingUser) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already in use by another user.',
                ]);
            }

            $user->email = $email;
        }

        if (isset($payload['name'])) {
            $user->name = trim((string) $payload['name']);
        }

        if (isset($payload['is_active'])) {
            $user->is_active = (bool) $payload['is_active'];
        }

        if (isset($payload['password']) && ! empty($payload['password'])) {
            $user->password = bcrypt($payload['password']);
        }

        $user->save();

        if (isset($payload['role_name'])) {
            $this->assignUserRoleAction->handle(
                $user,
                $payload['role_name'],
                $userId,
            );
        }

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'users.update',
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
                'changes' => array_keys($payload),
            ],
        );

        return $user;
    }
}
