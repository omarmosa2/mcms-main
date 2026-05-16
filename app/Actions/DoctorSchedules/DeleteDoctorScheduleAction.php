<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;

class DeleteDoctorScheduleAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $scheduleId, int $userId): void
    {
        $schedule = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->findOrFail($scheduleId);

        $oldValues = $schedule->only([
            'doctor_id',
            'day_of_week',
            'start_time',
            'end_time',
        ]);

        $schedule->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_schedules.delete',
            auditable: $schedule,
            oldValues: $oldValues,
        );
    }
}
