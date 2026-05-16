<?php

namespace App\Actions\Queue;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\QueueEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CallNextQueueEntryAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $userId, ?string $queueDate = null): ?QueueEntry
    {
        $normalizedDate = Carbon::parse((string) ($queueDate ?? now()->toDateString()))->toDateString();

        $entry = DB::transaction(function () use ($clinicId, $normalizedDate, $userId): ?QueueEntry {
            $nextEntry = QueueEntry::query()
                ->forClinic($clinicId)
                ->whereDate('queue_date', $normalizedDate)
                ->where('status', QueueEntry::STATUS_WAITING)
                ->orderByDesc('priority')
                ->orderBy('queue_number')
                ->lockForUpdate()
                ->first();

            if ($nextEntry === null) {
                return null;
            }

            $nextEntry->status = QueueEntry::STATUS_CALLED;
            $nextEntry->called_by = $userId;
            $nextEntry->called_at = now();
            $nextEntry->save();

            return $nextEntry;
        });

        if ($entry === null) {
            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'queue.call_next',
                metadata: [
                    'queue_date' => $normalizedDate,
                    'result' => 'none',
                ],
            );

            return null;
        }

        $entry->status = QueueEntry::STATUS_CALLED;
        $entry->called_by = $userId;
        $entry->called_at = now();
        $entry->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'queue.call_next',
            auditable: $entry,
            newValues: $entry->only(['status', 'called_by', 'called_at']),
            metadata: ['queue_date' => $normalizedDate],
        );

        return $entry->fresh();
    }
}
