<?php

namespace App\Services;

use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;

class DoctorScheduleService
{
    public function __construct(private DoctorAvailabilityService $doctorAvailabilityService) {}

    public function isDoctorAvailable(int $clinicId, int $doctorId, string $scheduledFor, int $durationMinutes): bool
    {
        $doctorProfileId = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('user_id', $doctorId)
            ->value('id');

        if ($doctorProfileId === null) {
            return true;
        }

        $hasSchedule = DoctorSchedule::query()
            ->where('clinic_id', $clinicId)
            ->where('doctor_profile_id', $doctorProfileId)
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
