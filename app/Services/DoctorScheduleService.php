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
            ->where('is_active', true)
            ->value('id');

        if ($doctorProfileId === null) {
            return false;
        }

        $hasSchedule = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctorProfileId)
            ->exists();

        if (! $hasSchedule) {
            return false;
        }

        return $this->doctorAvailabilityService->isDoctorAvailableForAppointment(
            $clinicId,
            $doctorId,
            $scheduledFor,
            $durationMinutes,
        );
    }
}
