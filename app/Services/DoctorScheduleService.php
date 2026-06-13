<?php

namespace App\Services;

use App\Models\DoctorSchedule;

class DoctorScheduleService
{
    public function __construct(private DoctorAvailabilityService $doctorAvailabilityService) {}

    public function isDoctorAvailable(int $clinicId, int $doctorId, string $scheduledFor, int $durationMinutes): bool
    {
        $hasSchedule = DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->exists();

        if (! $hasSchedule) {
            return true;
        }

        return $this->doctorAvailabilityService->isDoctorAvailableForAppointment(
            $clinicId,
            $doctorId,
            $scheduledFor,
            $durationMinutes,
        );
    }
}
