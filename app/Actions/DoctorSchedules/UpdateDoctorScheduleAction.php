<?php

namespace App\Actions\DoctorSchedules;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\DoctorSchedule;
use Illuminate\Validation\ValidationException;

class UpdateDoctorScheduleAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $scheduleId, int $userId, array $payload): DoctorSchedule
    {
        $schedule = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->findOrFail($scheduleId);

        if (array_key_exists('day_of_week', $payload)) {
            $existing = DoctorSchedule::query()
                ->where('clinic_id', $clinicId)
                ->where('doctor_id', $schedule->doctor_id)
                ->where('day_of_week', $payload['day_of_week'])
                ->where('id', '!=', $scheduleId)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'day_of_week' => 'جدول دوام هذا الطبيب لهذا اليوم موجود مسبقاً',
                ]);
            }
        }

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
