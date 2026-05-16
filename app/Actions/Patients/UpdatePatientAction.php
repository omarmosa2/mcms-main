<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class UpdatePatientAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private SyncPatientMedicalProfileAction $syncPatientMedicalProfileAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $patientId, int $userId, array $payload): Patient
    {
        return DB::transaction(function () use ($clinicId, $patientId, $userId, $payload): Patient {
            $patient = Patient::query()
                ->forClinic($clinicId)
                ->findOrFail($patientId);

            $oldValues = $patient->only([
                'file_number',
                'first_name',
                'last_name',
                'date_of_birth',
                'gender',
                'phone',
                'email',
                'emergency_contact_name',
                'emergency_contact_phone',
                'notes',
            ]);

            $oldMedicalCounts = [
                'chronic_conditions_count' => $patient->chronicConditions()->count(),
                'allergies_count' => $patient->allergies()->count(),
                'current_medications_count' => $patient->medications()->count(),
            ];

            $patientPayload = $this->extractPatientPayload($payload);
            $patient->fill($patientPayload);
            $patient->save();

            $this->syncPatientMedicalProfileAction->handle($patient, $payload);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'patients.update',
                auditable: $patient,
                oldValues: $oldValues,
                newValues: $patient->only([
                    'file_number',
                    'first_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'phone',
                    'email',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                    'notes',
                ]),
                metadata: [
                    ...$oldMedicalCounts,
                    'updated_chronic_conditions_count' => $patient->chronicConditions()->count(),
                    'updated_allergies_count' => $patient->allergies()->count(),
                    'updated_current_medications_count' => $patient->medications()->count(),
                ],
            );

            return $patient->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractPatientPayload(array $payload): array
    {
        unset(
            $payload['chronic_conditions'],
            $payload['allergies'],
            $payload['current_medications'],
        );

        return $payload;
    }
}
