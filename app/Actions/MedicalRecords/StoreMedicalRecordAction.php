<?php

namespace App\Actions\MedicalRecords;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\MedicalRecord;
use App\Models\TreatmentPlan;
use Illuminate\Support\Facades\DB;

class StoreMedicalRecordAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): MedicalRecord
    {
        return DB::transaction(function () use ($clinicId, $userId, $payload): MedicalRecord {
            $recordNumber = $this->generateRecordNumber($clinicId);

            $record = MedicalRecord::query()->create([
                'clinic_id' => $clinicId,
                'patient_id' => (int) $payload['patient_id'],
                'appointment_id' => $payload['appointment_id'] ?? null,
                'doctor_id' => $userId,
                'record_number' => $recordNumber,
                'clinic_type' => $payload['clinic_type'] ?? null,
                'form_data' => $payload['form_data'] ?? null,
                'chief_complaint' => $payload['chief_complaint'] ?? null,
                'primary_diagnosis' => $payload['primary_diagnosis'] ?? null,
                'secondary_diagnosis' => $payload['secondary_diagnosis'] ?? null,
                'clinical_notes' => $payload['clinical_notes'] ?? null,
                'examination' => $payload['examination'] ?? null,
                'status' => $payload['status'] ?? MedicalRecord::STATUS_DRAFT,
                'visit_date' => $payload['visit_date'] ?? now()->toDateString(),
                'created_by' => $userId,
            ]);

            if (! empty($payload['treatment_plans'])) {
                foreach ($payload['treatment_plans'] as $plan) {
                    TreatmentPlan::query()->create([
                        'clinic_id' => $clinicId,
                        'medical_record_id' => $record->id,
                        'patient_id' => (int) $payload['patient_id'],
                        'doctor_id' => $userId,
                        'title' => $plan['title'],
                        'description' => $plan['description'] ?? null,
                        'start_date' => $plan['start_date'] ?? null,
                        'end_date' => $plan['end_date'] ?? null,
                        'status' => $plan['status'] ?? TreatmentPlan::STATUS_NEW,
                        'created_by' => $userId,
                    ]);
                }
            }

            if (! empty($payload['follow_ups'])) {
                foreach ($payload['follow_ups'] as $followUp) {
                    FollowUp::query()->create([
                        'clinic_id' => $clinicId,
                        'medical_record_id' => $record->id,
                        'patient_id' => (int) $payload['patient_id'],
                        'doctor_id' => $userId,
                        'follow_up_date' => $followUp['follow_up_date'],
                        'notes' => $followUp['notes'] ?? null,
                        'recommended_action' => $followUp['recommended_action'] ?? null,
                        'status' => FollowUp::STATUS_SCHEDULED,
                        'created_by' => $userId,
                    ]);
                }
            }

            if (! empty($payload['appointment_id'])) {
                $appointment = Appointment::query()
                    ->forClinic($clinicId)
                    ->find($payload['appointment_id']);

                if ($appointment && $appointment->status !== Appointment::STATUS_COMPLETED) {
                    $appointment->update([
                        'status' => Appointment::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
                }
            }

            $record->load(['treatmentPlans', 'followUps']);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'medical_records.create',
                auditable: $record,
                newValues: $record->only([
                    'record_number',
                    'patient_id',
                    'clinic_id',
                    'clinic_type',
                    'status',
                ]),
            );

            return $record;
        });
    }

    private function generateRecordNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) MedicalRecord::query()
            ->forClinic($clinicId)
            ->whereDate('created_at', $today)
            ->count() + 1;

        return sprintf('MR-%s-%04d', now()->format('Ymd'), $sequence);
    }
}
