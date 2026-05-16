<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

class DeleteVisitAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $visitId, int $userId): void
    {
        $visit = Visit::query()
            ->forClinic($clinicId)
            ->findOrFail($visitId);

        if ($visit->status !== Visit::STATUS_STARTED) {
            throw ValidationException::withMessages([
                'status' => 'Only started visits can be deleted.',
            ]);
        }

        $oldValues = $visit->only([
            'visit_number',
            'status',
            'patient_id',
            'appointment_id',
            'queue_entry_id',
        ]);

        $visit->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'visits.delete',
            auditable: $visit,
            oldValues: $oldValues,
        );
    }
}
