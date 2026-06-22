<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;
use App\Services\DoctorScheduleValidationService;

class UpdateDoctorScheduleAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private DoctorScheduleValidationService $doctorScheduleValidationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $scheduleId, int $userId, array $payload): DoctorSchedule
    {
        $schedule = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->findOrFail($scheduleId);

        $this->doctorScheduleValidationService->validate(
            clinicId: $clinicId,
            doctorId: (int) $schedule->doctor_id,
            dayOfWeek: (int) ($payload['day_of_week'] ?? $schedule->day_of_week),
            startTime: (string) ($payload['start_time'] ?? $schedule->start_time),
            endTime: (string) ($payload['end_time'] ?? $schedule->end_time),
            isAvailable: (bool) ($payload['is_available'] ?? $schedule->is_available),
            ignoreScheduleId: $schedule->id,
        );

        $oldValues = $schedule->only([
            'day_of_week',
            'start_time',
            'end_time',
            'is_available',
        ]);

        $schedule->fill($payload);
        $schedule->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'doctor_schedules.update',
            auditable: $schedule,
            oldValues: $oldValues,
            newValues: $schedule->only([
                'day_of_week',
                'start_time',
                'end_time',
                'is_available',
            ]),
        );

        return $schedule->fresh();
    }
}
