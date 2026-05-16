<?php

namespace App\Actions\Patients;

use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncPatientMedicalProfileAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Patient $patient, array $payload): void
    {
        $this->syncMedicalItems(
            relation: $patient->chronicConditions(),
            payload: $payload,
            payloadKey: 'chronic_conditions',
            column: 'condition',
        );

        $this->syncMedicalItems(
            relation: $patient->allergies(),
            payload: $payload,
            payloadKey: 'allergies',
            column: 'allergy',
        );

        $this->syncMedicalItems(
            relation: $patient->medications(),
            payload: $payload,
            payloadKey: 'current_medications',
            column: 'medication',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncMedicalItems(HasMany $relation, array $payload, string $payloadKey, string $column): void
    {
        if (! array_key_exists($payloadKey, $payload)) {
            return;
        }

        $patient = $relation->getParent();
        $clinicId = $patient->getAttribute('clinic_id');
        $patientId = $patient->getKey();

        $items = $this->normalizeMedicalItems($payload[$payloadKey] ?? null);

        $relation->delete();

        if ($items === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                'clinic_id' => $clinicId,
                'patient_id' => $patientId,
                $column => $item,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $relation->getRelated()::query()->insert($rows);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMedicalItems(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $stringValue = trim((string) $item);

            if ($stringValue === '') {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return array_values(array_unique($normalized));
    }
}
