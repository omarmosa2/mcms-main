<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class UpdateVisitAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $visitId, int $userId, array $payload, ?int $actingDoctorId = null): Visit
    {
        $query = Visit::query()
            ->forClinic($clinicId);

        if ($actingDoctorId !== null) {
            $query->where('doctor_id', $actingDoctorId);
        }

        $visit = $query->findOrFail($visitId);

        if ($visit->status === Visit::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Completed visits cannot be edited.',
            ]);
        }

        if ($actingDoctorId !== null && array_key_exists('doctor_id', $payload) && (int) $payload['doctor_id'] !== $actingDoctorId) {
            throw ValidationException::withMessages([
                'doctor_id' => 'You can only manage visits assigned to your own doctor account.',
            ]);
        }

        if ($actingDoctorId !== null) {
            $payload['doctor_id'] = $actingDoctorId;
        }

        $patientId = array_key_exists('patient_id', $payload)
            ? (int) $payload['patient_id']
            : (int) $visit->patient_id;

        $this->ensurePatientBelongsToClinic($clinicId, $patientId);

        $appointment = array_key_exists('appointment_id', $payload)
            ? $this->resolveAppointmentIfProvided($clinicId, $payload['appointment_id'])
            : $this->resolveAppointmentIfProvided($clinicId, $visit->appointment_id);

        $queueEntry = array_key_exists('queue_entry_id', $payload)
            ? $this->resolveQueueEntryIfProvided($clinicId, $payload['queue_entry_id'])
            : $this->resolveQueueEntryIfProvided($clinicId, $visit->queue_entry_id);

        if (array_key_exists('doctor_id', $payload)) {
            $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id']);
        }

        $this->ensureMedicalReferencesAreConsistent(
            patientId: $patientId,
            appointment: $appointment,
            queueEntry: $queueEntry,
        );

        $payload['patient_id'] = $patientId;
        $payload['appointment_id'] = $appointment?->id;
        $payload['queue_entry_id'] = $queueEntry?->id;

        $oldValues = $visit->only([
            'queue_entry_id',
            'appointment_id',
            'patient_id',
            'doctor_id',
            'visit_number',
            'chief_complaint',
            'clinical_notes',
            'diagnosis_notes',
            'treatment_plan',
        ]);

        $visit->fill($payload);
        $visit->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'visits.update',
            auditable: $visit,
            oldValues: $oldValues,
            newValues: $visit->only([
                'queue_entry_id',
                'appointment_id',
                'patient_id',
                'doctor_id',
                'visit_number',
                'chief_complaint',
                'clinical_notes',
                'diagnosis_notes',
                'treatment_plan',
            ]),
        );

        return $visit->fresh();
    }

    private function ensurePatientBelongsToClinic(int $clinicId, int $patientId): void
    {
        $exists = Patient::query()->forClinic($clinicId)->whereKey($patientId)->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'patient_id' => 'The selected patient is not available for this clinic.',
            ]);
        }
    }

    private function resolveAppointmentIfProvided(int $clinicId, mixed $appointmentId): ?Appointment
    {
        if ($appointmentId === null) {
            return null;
        }

        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->whereKey((int) $appointmentId)
            ->first();

        if ($appointment === null) {
            throw ValidationException::withMessages([
                'appointment_id' => 'The selected appointment is not available for this clinic.',
            ]);
        }

        return $appointment;
    }

    private function resolveQueueEntryIfProvided(int $clinicId, mixed $queueEntryId): ?QueueEntry
    {
        if ($queueEntryId === null) {
            return null;
        }

        $queueEntry = QueueEntry::query()
            ->forClinic($clinicId)
            ->whereKey((int) $queueEntryId)
            ->first();

        if ($queueEntry === null) {
            throw ValidationException::withMessages([
                'queue_entry_id' => 'The selected queue entry is not available for this clinic.',
            ]);
        }

        return $queueEntry;
    }

    private function ensureDoctorBelongsToClinicIfProvided(int $clinicId, mixed $doctorId): void
    {
        if ($doctorId === null) {
            return;
        }

        $exists = User::query()
            ->where('clinic_id', $clinicId)
            ->whereKey((int) $doctorId)
            ->whereHas('roles', function ($query) use ($clinicId): void {
                $query
                    ->where('roles.clinic_id', $clinicId)
                    ->where('roles.name', 'doctor');
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'doctor_id' => 'The selected doctor is not available for this clinic.',
            ]);
        }
    }

    private function ensureMedicalReferencesAreConsistent(
        int $patientId,
        ?Appointment $appointment,
        ?QueueEntry $queueEntry,
    ): void {
        if ($appointment !== null && (int) $appointment->patient_id !== $patientId) {
            throw ValidationException::withMessages([
                'appointment_id' => 'The selected appointment does not belong to the selected patient.',
            ]);
        }

        if ($queueEntry !== null && (int) $queueEntry->patient_id !== $patientId) {
            throw ValidationException::withMessages([
                'queue_entry_id' => 'The selected queue entry does not belong to the selected patient.',
            ]);
        }

        if (
            $appointment !== null
            && $queueEntry !== null
            && $queueEntry->appointment_id !== null
            && (int) $queueEntry->appointment_id !== (int) $appointment->id
        ) {
            throw ValidationException::withMessages([
                'queue_entry_id' => 'The selected queue entry does not match the selected appointment.',
            ]);
        }
    }
}
