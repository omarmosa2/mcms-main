<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\QueueNumberSequence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnqueuePatientAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): QueueEntry
    {
        $patientId = (int) $payload['patient_id'];

        $this->ensurePatientBelongsToClinic($clinicId, $patientId);

        $appointment = $this->resolveAppointmentIfProvided($clinicId, $payload['appointment_id'] ?? null);

        if ($appointment !== null && (int) $appointment->patient_id !== $patientId) {
            throw ValidationException::withMessages([
                'appointment_id' => 'The selected appointment does not belong to the selected patient.',
            ]);
        }

        $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['assigned_doctor_id'] ?? null);

        $queueDate = Carbon::parse((string) ($payload['queue_date'] ?? now()->toDateString()))->toDateString();

        $entry = DB::transaction(function () use ($clinicId, $payload, $queueDate, $appointment, $patientId): QueueEntry {
            $nextQueueNumber = QueueNumberSequence::getNextValue($clinicId, $queueDate);

            return QueueEntry::query()->create([
                'clinic_id' => $clinicId,
                'appointment_id' => $appointment?->id,
                'patient_id' => $patientId,
                'assigned_doctor_id' => $payload['assigned_doctor_id'] ?? null,
                'called_by' => null,
                'queue_date' => $queueDate,
                'queue_number' => $nextQueueNumber,
                'priority' => (int) ($payload['priority'] ?? 0),
                'status' => QueueEntry::STATUS_WAITING,
                'checked_in_at' => now(),
                'called_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'notes' => $payload['notes'] ?? null,
            ]);
        });

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.enqueue',
            auditable: $entry,
            newValues: $entry->only([
                'clinic_id',
                'appointment_id',
                'patient_id',
                'queue_date',
                'queue_number',
                'priority',
                'status',
            ]),
        );

        return $entry;
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
                'assigned_doctor_id' => 'The selected doctor is not available for this clinic.',
            ]);
        }
    }
}
