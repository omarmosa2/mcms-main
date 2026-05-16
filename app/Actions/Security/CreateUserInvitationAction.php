<?php

namespace App\Actions\Security;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Role;
use App\Models\UserInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUserInvitationAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): UserInvitation
    {
        $roleName = (string) $payload['role_name'];
        $email = strtolower(trim((string) $payload['email']));

        $roleExists = Role::query()
            ->forClinic($clinicId)
            ->where('name', $roleName)
            ->exists();

        if (! $roleExists) {
            throw ValidationException::withMessages([
                'role_name' => 'The selected role is not available for this clinic.',
            ]);
        }

        $activeInvitationExists = UserInvitation::query()
            ->forClinic($clinicId)
            ->active()
            ->where('email', $email)
            ->exists();

        if ($activeInvitationExists) {
            throw ValidationException::withMessages([
                'email' => 'An active invitation already exists for this email.',
            ]);
        }

        $invitation = UserInvitation::query()->create([
            'clinic_id' => $clinicId,
            'invited_by' => $userId,
            'email' => $email,
            'full_name' => $payload['full_name'] ?? null,
            'role_name' => $roleName,
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('security.invitation_expiration_days', 7)),
            'accepted_at' => null,
            'accepted_user_id' => null,
            'metadata' => [
                'created_via' => 'settings.security',
            ],
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'security.invitations.create',
            auditable: $invitation,
            metadata: [
                'email' => $email,
                'role_name' => $roleName,
                'expires_at' => $invitation->expires_at?->toISOString(),
            ],
        );

        return $invitation;
    }
}
