<?php

namespace App\Actions\Fortify;

use App\Actions\Rbac\AssignUserRoleAction;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private AssignUserRoleAction $assignUserRoleAction) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $invitation = $this->resolveInvitation($input['invitation_token'] ?? null);
        $isPublicRegistrationEnabled = (bool) config('security.public_registration_enabled', false);

        if (! $isPublicRegistrationEnabled && $invitation === null) {
            throw ValidationException::withMessages([
                'invitation_token' => 'Public registration is disabled. A valid invitation token is required.',
            ]);
        }

        if ($invitation !== null && ! $invitation->isActive()) {
            throw ValidationException::withMessages([
                'invitation_token' => 'The provided invitation token is no longer valid.',
            ]);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules($invitation?->clinic_id),
            'invitation_token' => ['nullable', 'string'],
        ])->after(function ($validator) use ($invitation): void {
            $email = strtolower(trim((string) ($validator->getData()['email'] ?? '')));

            if ($invitation !== null && $email !== strtolower($invitation->email)) {
                $validator->errors()->add('email', 'The provided email does not match the invitation.');
            }
        })->validate();

        return DB::transaction(function () use ($input, $invitation): User {
            $activeInvitation = null;

            if ($invitation !== null) {
                $activeInvitation = UserInvitation::query()
                    ->whereKey($invitation->id)
                    ->lockForUpdate()
                    ->first();

                if ($activeInvitation === null || ! $activeInvitation->isActive()) {
                    throw ValidationException::withMessages([
                        'invitation_token' => 'The provided invitation token has already been consumed.',
                    ]);
                }
            }

            $user = User::create([
                'clinic_id' => $activeInvitation?->clinic_id,
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'email_verified_at' => $activeInvitation !== null ? now() : null,
            ]);

            if ($activeInvitation !== null) {
                $this->assignUserRoleAction->handle(
                    user: $user,
                    roleName: $activeInvitation->role_name,
                    assignedBy: $activeInvitation->invited_by,
                );

                $activeInvitation->accepted_at = now();
                $activeInvitation->accepted_user_id = $user->id;
                $activeInvitation->save();
            }

            return $user;
        });
    }

    private function resolveInvitation(mixed $token): ?UserInvitation
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        return UserInvitation::query()
            ->where('token', trim($token))
            ->first();
    }
}
