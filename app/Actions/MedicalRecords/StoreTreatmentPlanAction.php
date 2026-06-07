<?php

namespace App\Actions\MedicalRecords;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\TreatmentPlan;

class StoreTreatmentPlanAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): TreatmentPlan
    {
        $plan = TreatmentPlan::query()->create([
            'clinic_id' => $clinicId,
            'medical_record_id' => (int) $payload['medical_record_id'],
            'patient_id' => (int) $payload['patient_id'],
            'doctor_id' => $userId,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'start_date' => $payload['start_date'] ?? null,
            'end_date' => $payload['end_date'] ?? null,
            'status' => $payload['status'] ?? TreatmentPlan::STATUS_NEW,
            'created_by' => $userId,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'treatment_plans.create',
            auditable: $plan,
            newValues: $plan->only([
                'medical_record_id',
                'patient_id',
                'title',
                'status',
            ]),
        );

        return $plan;
    }
}
