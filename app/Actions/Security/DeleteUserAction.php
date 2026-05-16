<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\User;

class DeleteUserAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $targetUserId,
    ): void {
        $user = User::query()
            ->forClinic($clinicId)
            ->where('id', $targetUserId)
            ->firstOrFail();

        if ($user->id === $userId) {
            abort(422, 'You cannot delete your own account.');
        }

        $deletedUserId = $user->id;
        $userEmail = $user->email;
        $userName = $user->name;

        $user->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'users.delete',
            metadata: [
                'deleted_user_id' => $deletedUserId,
                'deleted_email' => $userEmail,
                'deleted_name' => $userName,
            ],
        );
    }
}
