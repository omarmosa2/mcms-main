<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\QueueEntry;
use Illuminate\Validation\ValidationException;

class UpdateQueueEntryStatusAction extends BaseAction
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        QueueEntry::STATUS_WAITING => [
            QueueEntry::STATUS_CALLED,
            QueueEntry::STATUS_SKIPPED,
            QueueEntry::STATUS_CANCELED,
        ],
        QueueEntry::STATUS_CALLED => [
            QueueEntry::STATUS_IN_SERVICE,
            QueueEntry::STATUS_SKIPPED,
            QueueEntry::STATUS_CANCELED,
        ],
        QueueEntry::STATUS_IN_SERVICE => [
            QueueEntry::STATUS_COMPLETED,
            QueueEntry::STATUS_CANCELED,
        ],
        QueueEntry::STATUS_COMPLETED => [],
        QueueEntry::STATUS_SKIPPED => [],
        QueueEntry::STATUS_CANCELED => [],
    ];

    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $queueEntryId, int $userId, string $newStatus, ?string $notes = null): QueueEntry
    {
        $entry = QueueEntry::query()
            ->forClinic($clinicId)
            ->findOrFail($queueEntryId);

        $currentStatus = $entry->status;

        if (! in_array($newStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid queue status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $entry->only([
            'status',
            'called_by',
            'called_at',
            'started_at',
            'completed_at',
            'notes',
        ]);

        $entry->status = $newStatus;

        if ($newStatus === QueueEntry::STATUS_CALLED) {
            $entry->called_by = $userId;
            $entry->called_at = now();
        }

        if ($newStatus === QueueEntry::STATUS_IN_SERVICE) {
            $entry->started_at = now();
        }

        if ($newStatus === QueueEntry::STATUS_COMPLETED) {
            $entry->completed_at = now();
        }

        if ($notes !== null) {
            $entry->notes = $notes;
        }

        $entry->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.update_status',
            auditable: $entry,
            oldValues: $oldValues,
            newValues: $entry->only([
                'status',
                'called_by',
                'called_at',
                'started_at',
                'completed_at',
                'notes',
            ]),
        );

        return $entry->fresh();
    }
}
