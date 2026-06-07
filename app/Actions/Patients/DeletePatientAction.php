<?php

namespace App\Actions\Patients;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

class DeletePatientAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $patientId, int $userId): void
    {
        $patient = Patient::query()
            ->forClinic($clinicId)
            ->withCount([
                'appointments',
                'invoices',
            ])
            ->findOrFail($patientId);

        if (
            (int) $patient->appointments_count > 0
            || (int) $patient->invoices_count > 0
        ) {
            throw ValidationException::withMessages([
                'patient' => 'Cannot delete a patient with medical or financial history.',
            ]);
        }

        $oldValues = $patient->only([
            'file_number',
            'first_name',
            'last_name',
            'phone',
            'email',
        ]);

        $patient->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'patients.delete',
            auditable: $patient,
            oldValues: $oldValues,
        );
    }
}
