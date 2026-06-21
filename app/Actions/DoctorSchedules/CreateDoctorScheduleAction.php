<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;
use App\Services\DoctorScheduleValidationService;

class CreateDoctorScheduleAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private DoctorScheduleValidationService $doctorScheduleValidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): DoctorSchedule
    {
        $this->doctorScheduleValidationService->validate(
            clinicId: $clinicId,
            doctorId: (int) $payload['doctor_id'],
            dayOfWeek: (int) $payload['day_of_week'],
            startTime: (string) $payload['start_time'],
            endTime: (string) $payload['end_time'],
            isAvailable: (bool) ($payload['is_available'] ?? true),
        );

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
