<?php

namespace App\Listeners;

use App\Actions\Auth\LogAuthAttemptAction;
use App\Models\AuthAttemptLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;

class LogAuthenticationAttempts
{
    public function __construct(private LogAuthAttemptAction $logAuthAttemptAction) {}

    public function handle(object $event): void
    {
        if ($event instanceof Login) {
            $this->handleSuccessfulLogin($event);

            return;
        }

        if ($event instanceof Failed) {
            $this->handleFailedLogin($event);

            return;
        }

        if ($event instanceof Lockout) {
            $this->handleLockout($event);
        }
    }

    private function handleSuccessfulLogin(Login $event): void
    {
        $request = request();
        $user = $event->user;

        $this->logAuthAttemptAction->handle(
            clinicId: $user->clinic_id !== null ? (int) $user->clinic_id : null,
            userId: (int) $user->id,
            email: $user->email,
            status: AuthAttemptLog::STATUS_SUCCESS,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function handleFailedLogin(Failed $event): void
    {
        $request = request();
        $user = $event->user instanceof User ? $event->user : null;
        $email = $this->resolveAttemptEmail($event->credentials);
        $matchedUser = $user ?? $this->resolveUserByEmail($email);

        $this->logAuthAttemptAction->handle(
            clinicId: $matchedUser?->clinic_id !== null ? (int) $matchedUser->clinic_id : null,
            userId: $matchedUser?->id !== null ? (int) $matchedUser->id : null,
            email: $email,
            status: AuthAttemptLog::STATUS_FAILED,
            failureReason: 'invalid_credentials',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function handleLockout(Lockout $event): void
    {
        $request = $event->request;
        $email = $this->resolveAttemptEmail($request->all());
        $matchedUser = $this->resolveUserByEmail($email);

        $this->logAuthAttemptAction->handle(
            clinicId: $matchedUser?->clinic_id !== null ? (int) $matchedUser->clinic_id : null,
            userId: $matchedUser?->id !== null ? (int) $matchedUser->id : null,
            email: $email,
            status: AuthAttemptLog::STATUS_LOCKOUT,
            failureReason: 'too_many_attempts',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function resolveAttemptEmail(array $credentials): ?string
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email)) {
            return null;
        }

        $normalized = trim(strtolower($email));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveUserByEmail(?string $email): ?User
    {
        if ($email === null) {
            return null;
        }

        return User::query()
            ->where('email', $email)
            ->first();
    }
}
