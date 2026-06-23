<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\User;

class ResetUserPasswordAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(User $administrator, int $targetUserId, string $password): User
    {
        $user = User::query()->findOrFail($targetUserId);

        $user->password = $password;
        $user->save();

        $this->logAuditAction->handle(
            clinicId: (int) ($user->clinic_id ?? $administrator->clinic_id),
            userId: $administrator->id,
            action: 'users.password_reset',
            auditable: $user,
            metadata: [
                'target_user_id' => $user->id,
            ],
        );

        return $user;
    }
}
