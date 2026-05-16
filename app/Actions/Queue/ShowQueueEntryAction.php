<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Builder;

class ShowQueueEntryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $queueEntryId, int $userId, ?int $doctorId = null): QueueEntry
    {
        $query = QueueEntry::query()
            ->forClinic($clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'appointment:id,clinic_id,appointment_number',
                'assignedDoctor:id,clinic_id,name',
                'calledBy:id,clinic_id,name',
            ]);

        if ($doctorId !== null) {
            $query->where(function (Builder $builder) use ($doctorId): void {
                $builder
                    ->where('assigned_doctor_id', $doctorId)
                    ->orWhereHas('visit', function (Builder $visitQuery) use ($doctorId): void {
                        $visitQuery->where('doctor_id', $doctorId);
                    });
            });
        }

        $entry = $query->findOrFail($queueEntryId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.show',
            auditable: $entry,
        );

        return $entry;
    }
}
