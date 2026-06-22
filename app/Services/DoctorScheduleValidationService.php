<?php

namespace App\Services;

use App\Models\ClinicWorkingHour;
use App\Models\DoctorSchedule;
use Illuminate\Validation\ValidationException;

class DoctorScheduleValidationService
{
    public function validate(
        int $clinicId,
        int $doctorId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        bool $isAvailable = true,
        ?int $ignoreScheduleId = null,
    ): void {
        if (! $isAvailable) {
            return;
        }

        $clinicHour = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if ($clinicHour === null || $clinicHour->start_time === null || $clinicHour->end_time === null) {
            throw ValidationException::withMessages([
                'day_of_week' => 'لا يمكن تحديد دوام لطبيب في يوم مغلق للعيادة.',
            ]);
        }

        $clinicStart = $this->formatTime($clinicHour->start_time);
        $clinicEnd = $this->formatTime($clinicHour->end_time);

        if ($startTime < $clinicStart || $endTime > $clinicEnd) {
            throw ValidationException::withMessages([
                'start_time' => "دوام الطبيب يجب أن يكون ضمن دوام العيادة من {$clinicStart} إلى {$clinicEnd}.",
            ]);
        }

        $overlaps = DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_profile_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->when($ignoreScheduleId !== null, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_time' => 'يتداخل هذا الوقت مع دوام محفوظ للطبيب في اليوم نفسه.',
            ]);
        }
    }

    private function formatTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
