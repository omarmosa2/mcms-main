<?php

namespace App\Actions\Auth;

use App\Actions\BaseAction;
use App\Models\AuthAttemptLog;

class LogAuthAttemptAction extends BaseAction
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        ?int $clinicId,
        ?int $userId,
        ?string $email,
        string $status,
        ?string $failureReason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $metadata = null,
    ): AuthAttemptLog {
        return AuthAttemptLog::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'email' => $email,
            'status' => $status,
            'failure_reason' => $failureReason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
