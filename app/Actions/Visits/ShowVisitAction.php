<?php

namespace App\Actions\Visits;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Visit;

class ShowVisitAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $visitId, int $userId, ?int $doctorId = null): Visit
    {
        $query = Visit::query()
            ->forClinic($clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'doctor:id,clinic_id,name',
                'appointment:id,clinic_id,appointment_number,status',
                'queueEntry:id,clinic_id,queue_date,queue_number,status',
            ]);

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        $visit = $query->findOrFail($visitId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'visits.show',
            auditable: $visit,
        );

        return $visit;
    }
}
