<?php

namespace App\Actions\MedicalRecords;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\MedicalRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateMedicalRecordAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $medicalRecordId, int $userId, array $payload): MedicalRecord
    {
        $record = MedicalRecord::query()
            ->forClinic($clinicId)
            ->whereKey($medicalRecordId)
            ->firstOrFail();

        if ($record->trashed()) {
            throw (new ModelNotFoundException)->setModel(MedicalRecord::class, [$medicalRecordId]);
        }

        $oldValues = $record->only([
            'form_data',
            'chief_complaint',
            'primary_diagnosis',
            'secondary_diagnosis',
            'clinical_notes',
            'examination',
            'status',
            'visit_date',
        ]);

        $updateData = array_filter([
            'department_id' => $payload['department_id'] ?? null,
            'clinic_type' => $payload['clinic_type'] ?? null,
            'form_data' => $payload['form_data'] ?? null,
            'chief_complaint' => array_key_exists('chief_complaint', $payload) ? $payload['chief_complaint'] : null,
            'primary_diagnosis' => array_key_exists('primary_diagnosis', $payload) ? $payload['primary_diagnosis'] : null,
            'secondary_diagnosis' => array_key_exists('secondary_diagnosis', $payload) ? $payload['secondary_diagnosis'] : null,
            'clinical_notes' => array_key_exists('clinical_notes', $payload) ? $payload['clinical_notes'] : null,
            'examination' => array_key_exists('examination', $payload) ? $payload['examination'] : null,
            'status' => $payload['status'] ?? null,
            'visit_date' => $payload['visit_date'] ?? null,
            'updated_by' => $userId,
        ], fn ($value): bool => $value !== null);

        $record->update($updateData);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'medical_records.update',
            auditable: $record,
            oldValues: $oldValues,
            newValues: $record->only([
                'form_data',
                'chief_complaint',
                'primary_diagnosis',
                'secondary_diagnosis',
                'clinical_notes',
                'examination',
                'status',
                'visit_date',
            ]),
        );

        return $record->fresh();
    }
}
