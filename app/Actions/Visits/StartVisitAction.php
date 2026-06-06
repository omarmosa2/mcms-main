<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\User;
use App\Models\Visit;
use App\Services\Cache\CacheService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartVisitAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private CacheService $cacheService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload, ?int $actingDoctorId = null): Visit
    {
        if ($actingDoctorId !== null) {
            if (array_key_exists('doctor_id', $payload) && $payload['doctor_id'] !== null && (int) $payload['doctor_id'] !== $actingDoctorId) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'You can only start visits under your own doctor account.',
                ]);
            }

            $payload['doctor_id'] = $actingDoctorId;
        }

        $attempts = 0;

        while ($attempts < 3) {
            try {
                return DB::transaction(function () use ($clinicId, $userId, $payload): Visit {
                    $patientId = (int) $payload['patient_id'];

                    $this->ensurePatientBelongsToClinic($clinicId, $patientId);

                    $appointment = $this->resolveAppointmentIfProvided($clinicId, $payload['appointment_id'] ?? null);
                    $queueEntry = $this->ensureQueueEntryBelongsToClinicIfProvided($clinicId, $payload['queue_entry_id'] ?? null, true);
                    $this->ensureDoctorBelongsToClinicIfProvided($clinicId, $payload['doctor_id'] ?? null);

                    $appointmentId = $appointment?->id;

                    if ($queueEntry !== null && $appointmentId === null && $queueEntry->appointment_id !== null) {
                        $appointmentId = (int) $queueEntry->appointment_id;
                        $appointment = $this->resolveAppointmentIfProvided($clinicId, $appointmentId);
                    }

                    $this->ensureAppointmentCanStartVisit($clinicId, $appointment);

                    $this->ensureMedicalReferencesAreConsistent(
                        patientId: $patientId,
                        appointment: $appointment,
                        queueEntry: $queueEntry,
                    );

                    $visitNumber = (string) ($payload['visit_number'] ?? $this->generateVisitNumber($clinicId));

                    $visit = Visit::query()->create([
                        'clinic_id' => $clinicId,
                        'queue_entry_id' => $queueEntry?->id,
                        'appointment_id' => $appointmentId,
                        'patient_id' => $patientId,
                        'doctor_id' => $payload['doctor_id'] ?? null,
                        'visit_number' => $visitNumber,
                        'status' => Visit::STATUS_STARTED,
                        'started_at' => now(),
                        'in_progress_at' => null,
                        'completed_at' => null,
                        'chief_complaint' => $payload['chief_complaint'] ?? null,
                        'clinical_notes' => $payload['clinical_notes'] ?? null,
                        'diagnosis_notes' => $payload['diagnosis_notes'] ?? null,
                        'treatment_plan' => $payload['treatment_plan'] ?? null,
                    ]);

                    if ($queueEntry !== null) {
                        $queueEntry->status = QueueEntry::STATUS_IN_SERVICE;
                        $queueEntry->started_at = now();
                        $queueEntry->save();
                    }

                    if ($appointment !== null) {
                        $oldAppointmentValues = $appointment->only([
                            'status',
                            'completed_at',
                        ]);

                        $appointment->status = Appointment::STATUS_COMPLETED;
                        $appointment->completed_at = now();
                        $appointment->save();

                        $this->logAuditAction->handle(
                            clinicId: $clinicId,
                            userId: $userId,
                            action: 'appointments.convert_to_visit',
                            auditable: $appointment,
                            oldValues: $oldAppointmentValues,
                            newValues: $appointment->only([
                                'status',
                                'completed_at',
                            ]),
                            metadata: [
                                'visit_id' => $visit->id,
                            ],
                        );
                    }

                    $this->logAuditAction->handle(
                        clinicId: $clinicId,
                        userId: $userId,
                        action: 'visits.start',
                        auditable: $visit,
                        newValues: $visit->only([
                            'clinic_id',
                            'queue_entry_id',
                            'appointment_id',
                            'patient_id',
                            'doctor_id',
                            'visit_number',
                            'status',
                        ]),
                    );

                    $this->cacheService->invalidateDashboardStats($clinicId);
                    $this->cacheService->invalidateDropdowns($clinicId);

                    return $visit;
                });
            } catch (QueryException $exception) {
                $attempts++;

                if (! $this->isUniqueConstraintViolation($exception) || $attempts >= 3) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'visit_number' => 'Unable to generate a visit number at this time. Please retry.',
        ]);
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

    private function ensureAppointmentCanStartVisit(int $clinicId, ?Appointment $appointment): void
    {
        if ($appointment === null) {
            return;
        }

        if (in_array($appointment->status, Appointment::TERMINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'appointment_id' => 'Cannot start a visit from a terminal appointment.',
            ]);
        }

        $visitExists = Visit::query()
            ->forClinic($clinicId)
            ->where('appointment_id', $appointment->id)
            ->exists();

        if ($visitExists) {
            throw ValidationException::withMessages([
                'appointment_id' => 'A visit already exists for the selected appointment.',
            ]);
        }
    }

    private function ensureQueueEntryBelongsToClinicIfProvided(int $clinicId, mixed $queueEntryId, bool $lockForUpdate = false): ?QueueEntry
    {
        if ($queueEntryId === null) {
            return null;
        }

        $query = QueueEntry::query()
            ->forClinic($clinicId)
            ->whereKey((int) $queueEntryId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $entry = $query->first();

        if ($entry === null) {
            throw ValidationException::withMessages([
                'queue_entry_id' => 'The selected queue entry is not available for this clinic.',
            ]);
        }

        if (in_array($entry->status, QueueEntry::TERMINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'queue_entry_id' => 'Cannot start a visit from a terminal queue entry.',
            ]);
        }

        return $entry;
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

    private function generateVisitNumber(int $clinicId): string
    {
        $today = now()->toDateString();
        $sequence = (int) Visit::query()
            ->forClinic($clinicId)
            ->whereDate('started_at', $today)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('VIS-%s-%04d', now()->format('Ymd'), $sequence);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $sqlState === '23000' || $sqlState === '23505';
    }
}
