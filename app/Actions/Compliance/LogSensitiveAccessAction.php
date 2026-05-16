<?php

namespace App\Actions\Compliance;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\SensitiveAccessLog;

class LogSensitiveAccessAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function handle(
        int $clinicId,
        int $userId,
        string $resourceType,
        ?int $resourceId = null,
        ?int $patientId = null,
        ?string $reason = null,
        ?array $context = null,
    ): SensitiveAccessLog {
        $log = SensitiveAccessLog::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'patient_id' => $patientId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'reason' => $reason,
            'context' => $context,
            'accessed_at' => now(),
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'compliance.sensitive_access',
            auditable: $log,
            metadata: [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'patient_id' => $patientId,
                'reason' => $reason,
            ],
        );

        return $log;
    }
}
