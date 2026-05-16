<?php

namespace App\Services;

use App\Models\DoctorSchedule;
use Illuminate\Support\Carbon;

class DoctorScheduleService
{
    public function isDoctorAvailable(int $clinicId, int $doctorId, string $scheduledFor, int $durationMinutes): bool
    {
        $dateTime = Carbon::parse($scheduledFor);
        $dayOfWeek = $dateTime->dayOfWeek;
        $time = $dateTime->format('H:i:s');

        $schedule = DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $schedule) {
            return true;
        }

        if (! $schedule->is_available) {
            return false;
        }

        $startTime = $schedule->start_time;
        $endTime = $schedule->end_time;

        $appointmentEnd = Carbon::parse($scheduledFor)->addMinutes($durationMinutes);

        return $time >= $startTime && $appointmentEnd->format('H:i:s') <= $endTime;
    }
}
