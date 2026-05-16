<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Actions\GenerateNumberAction;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class CreatePatientAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private SyncPatientMedicalProfileAction $syncPatientMedicalProfileAction,
        private GenerateNumberAction $generateNumberAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): Patient
    {
        return DB::transaction(function () use ($clinicId, $userId, $payload): Patient {
            $patientPayload = $this->extractPatientPayload($payload);

            $fileNumber = $this->generateNumberAction->handle(
                $clinicId,
                GenerateNumberAction::ENTITY_PATIENT,
                $patientPayload['file_number'] ?? null,
            );

            $patient = Patient::query()->create([
                ...$patientPayload,
                'clinic_id' => $clinicId,
                'file_number' => $fileNumber,
            ]);

            $this->syncPatientMedicalProfileAction->handle($patient, $payload);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'patients.create',
                auditable: $patient,
                newValues: $patient->only([
                    'clinic_id',
                    'file_number',
                    'first_name',
                    'last_name',
                    'date_of_birth',
                    'gender',
                    'phone',
                    'email',
                    'emergency_contact_name',
                    'emergency_contact_phone',
                ]),
                metadata: [
                    'chronic_conditions_count' => $patient->chronicConditions()->count(),
                    'allergies_count' => $patient->allergies()->count(),
                    'current_medications_count' => $patient->medications()->count(),
                ],
            );

            return $patient;
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
