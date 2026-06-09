<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;

class CreateDoctorScheduleAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): DoctorSchedule
    {
        $schedule = DoctorSchedule::query()->create([
            ...$payload,
            'clinic_id' => $clinicId,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_schedules.create',
            auditable: $schedule,
            newValues: $schedule->only([
                'clinic_id',
                'doctor_id',
                'day_of_week',
                'start_time',
                'end_time',
                'is_available',
            ]),
        );

        return $schedule;
    }
}
