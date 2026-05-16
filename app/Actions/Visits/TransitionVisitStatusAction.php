<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Appointment;
use App\Models\QueueEntry;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class TransitionVisitStatusAction extends BaseAction
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Visit::STATUS_STARTED => [Visit::STATUS_IN_PROGRESS],
        Visit::STATUS_IN_PROGRESS => [Visit::STATUS_COMPLETED],
        Visit::STATUS_COMPLETED => [],
    ];

    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $visitId, int $userId, string $newStatus, ?int $actingDoctorId = null): Visit
    {
        $query = Visit::query()
            ->forClinic($clinicId);

        if ($actingDoctorId !== null) {
            $query->where('doctor_id', $actingDoctorId);
        }

        $visit = $query->findOrFail($visitId);

        $currentStatus = $visit->status;

        if (! in_array($newStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid visit status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $visit->only([
            'status',
            'in_progress_at',
            'completed_at',
        ]);

        $visit->status = $newStatus;

        if ($newStatus === Visit::STATUS_IN_PROGRESS) {
            $visit->in_progress_at = now();
        }

        if ($newStatus === Visit::STATUS_COMPLETED) {
            $visit->completed_at = now();
            $this->syncLinkedQueueEntryToCompleted($clinicId, $visit);
            $this->syncLinkedAppointmentToCompleted($clinicId, $visit);
        }

        $visit->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'visits.transition_status',
            auditable: $visit,
            oldValues: $oldValues,
            newValues: $visit->only([
                'status',
                'in_progress_at',
                'completed_at',
            ]),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $visit->fresh();
    }

    private function syncLinkedQueueEntryToCompleted(int $clinicId, Visit $visit): void
    {
        if ($visit->queue_entry_id === null) {
            return;
        }

        $queueEntry = QueueEntry::query()
            ->forClinic($clinicId)
            ->whereKey($visit->queue_entry_id)
            ->first();

        if ($queueEntry === null) {
            return;
        }

        $queueEntry->status = QueueEntry::STATUS_COMPLETED;
        $queueEntry->completed_at = now();
        $queueEntry->save();
    }

    private function syncLinkedAppointmentToCompleted(int $clinicId, Visit $visit): void
    {
        if ($visit->appointment_id === null) {
            return;
        }

        $appointment = Appointment::query()
            ->forClinic($clinicId)
            ->whereKey($visit->appointment_id)
            ->first();

        if ($appointment === null) {
            return;
        }

        $appointment->status = Appointment::STATUS_COMPLETED;
        $appointment->completed_at = now();
        $appointment->save();
    }
}
