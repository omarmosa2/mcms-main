<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;
use Illuminate\Validation\ValidationException;

class CreateDoctorScheduleAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $userId, array $payload): DoctorSchedule
    {
        $existing = DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $payload['doctor_id'])
            ->where('day_of_week', $payload['day_of_week'])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'day_of_week' => 'جدول دوام هذا الطبيب لهذا اليوم موجود مسبقاً',
            ]);
        }

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
