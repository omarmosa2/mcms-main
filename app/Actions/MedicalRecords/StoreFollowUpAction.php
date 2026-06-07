<?php

namespace App\Actions\MedicalRecords;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\FollowUp;

class StoreFollowUpAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): FollowUp
    {
        $followUp = FollowUp::query()->create([
            'clinic_id' => $clinicId,
            'medical_record_id' => $payload['medical_record_id'] ?? null,
            'patient_id' => (int) $payload['patient_id'],
            'doctor_id' => $userId,
            'follow_up_date' => $payload['follow_up_date'],
            'notes' => $payload['notes'] ?? null,
            'recommended_action' => $payload['recommended_action'] ?? null,
            'status' => $payload['status'] ?? FollowUp::STATUS_SCHEDULED,
            'created_by' => $userId,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'follow_ups.create',
            auditable: $followUp,
            newValues: $followUp->only([
                'patient_id',
                'follow_up_date',
                'status',
            ]),
        );

        return $followUp;
    }
}
