<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\QueueEntry;
use Illuminate\Validation\ValidationException;

class DeleteQueueEntryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $queueEntryId, int $userId): void
    {
        $entry = QueueEntry::query()
            ->forClinic($clinicId)
            ->findOrFail($queueEntryId);

        if (! in_array($entry->status, [QueueEntry::STATUS_WAITING, QueueEntry::STATUS_SKIPPED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only waiting or skipped queue entries can be deleted.',
            ]);
        }

        $oldValues = $entry->only([
            'queue_date',
            'queue_number',
            'status',
            'patient_id',
            'appointment_id',
        ]);

        $entry->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.delete',
            auditable: $entry,
            oldValues: $oldValues,
        );
    }
}
