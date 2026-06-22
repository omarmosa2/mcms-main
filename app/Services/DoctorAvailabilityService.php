<?php

namespace App\Services;

use App\Models\ClinicWorkingHour;
use App\Models\DoctorLeave;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DoctorAvailabilityService
{
    /**
     * @return array{
     *     is_available: bool,
     *     unavailable_all_day: bool,
     *     available_periods: array<int, array{start_time: string, end_time: string}>,
     *     unavailable_periods: array<int, array{start_time: string, end_time: string, reason: ?string}>
     * }
     */
    public function availabilityForDay(int $clinicId, int $doctorId, Carbon|string $date): array
    {
        $carbonDate = CarbonImmutable::parse($date);
        $day = (int) $carbonDate->dayOfWeek;

        $doctorProfileId = $this->doctorProfileId($clinicId, $doctorId);

        if ($doctorProfileId === null) {
            return $this->emptyAvailability();
        }

        $doctorSchedules = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->where('doctor_profile_id', $doctorProfileId)
            ->where('day_of_week', $day)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get();

        if ($doctorSchedules->isEmpty()) {
            return $this->emptyAvailability();
        }

        $clinicHours = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->first();

        if ($clinicHours === null || $clinicHours->start_time === null || $clinicHours->end_time === null) {
            return $this->emptyAvailability();
        }

        $basePeriods = $doctorSchedules
            ->map(fn (DoctorSchedule $schedule): array => [
                'start_time' => max($this->formatTime($schedule->start_time), $this->formatTime($clinicHours->start_time)),
                'end_time' => min($this->formatTime($schedule->end_time), $this->formatTime($clinicHours->end_time)),
            ])
            ->filter(fn (array $period): bool => $period['start_time'] < $period['end_time'])
            ->values();

        if ($basePeriods->isEmpty()) {
            return $this->emptyAvailability();
        }

        $leaves = $this->activeLeaves($clinicId, $doctorId, $carbonDate->toDateString());

        if ($leaves->contains(fn (DoctorLeave $leave): bool => $leave->type === DoctorLeave::TYPE_FULL_DAY)) {
            return [
                'is_available' => false,
                'unavailable_all_day' => true,
                'available_periods' => [],
                'unavailable_periods' => [],
            ];
        }

        $blockedPeriods = $leaves
            ->filter(fn (DoctorLeave $leave): bool => $leave->type === DoctorLeave::TYPE_HOURLY)
            ->map(fn (DoctorLeave $leave): array => [
                'start_time' => $this->formatTime($leave->start_time),
                'end_time' => $this->formatTime($leave->end_time),
                'reason' => $leave->reason,
            ])
            ->sortBy('start_time')
            ->values();

        $availablePeriods = $basePeriods
            ->flatMap(fn (array $period): array => $this->subtractBlockedPeriods(
                $period['start_time'],
                $period['end_time'],
                $blockedPeriods,
            ))
            ->values()
            ->all();

        return [
            'is_available' => $availablePeriods !== [],
            'unavailable_all_day' => false,
            'available_periods' => $availablePeriods,
            'unavailable_periods' => $blockedPeriods->all(),
        ];
    }

    public function isDoctorAvailableForAppointment(
        int $clinicId,
        int $doctorId,
        mixed $scheduledFor,
        int $durationMinutes,
    ): bool {
        $start = CarbonImmutable::parse($scheduledFor);
        $end = $start->addMinutes($durationMinutes);

        $availability = $this->availabilityForDay($clinicId, $doctorId, $start);

        if (! $availability['is_available']) {
            return false;
        }

        $appointmentStart = $start->format('H:i');
        $appointmentEnd = $end->format('H:i');

        foreach ($availability['available_periods'] as $period) {
            if ($appointmentStart >= $period['start_time'] && $appointmentEnd <= $period['end_time']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, DoctorLeave>
     */
    private function activeLeaves(int $clinicId, int $doctorId, string $date): Collection
    {
        return DoctorLeave::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('leave_date', $date)
            ->where('status', DoctorLeave::STATUS_ACTIVE)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  Collection<int, array{start_time: string, end_time: string, reason: ?string}>  $blockedPeriods
     * @return array<int, array{start_time: string, end_time: string}>
     */
    private function subtractBlockedPeriods(string $baseStart, string $baseEnd, Collection $blockedPeriods): array
    {
        $availablePeriods = [];
        $cursor = $baseStart;

        foreach ($blockedPeriods as $period) {
            if ($cursor < $period['start_time']) {
                $availablePeriods[] = [
                    'start_time' => $cursor,
                    'end_time' => $period['start_time'],
                ];
            }

            if ($cursor < $period['end_time']) {
                $cursor = $period['end_time'];
            }
        }

        if ($cursor < $baseEnd) {
            $availablePeriods[] = [
                'start_time' => $cursor,
                'end_time' => $baseEnd,
            ];
        }

        return $availablePeriods;
    }

    /**
     * @return array{
     *     is_available: false,
     *     unavailable_all_day: false,
     *     available_periods: array<int, array{start_time: string, end_time: string}>,
     *     unavailable_periods: array<int, array{start_time: string, end_time: string, reason: ?string}>
     * }
     */
    private function emptyAvailability(): array
    {
        return [
            'is_available' => false,
            'unavailable_all_day' => false,
            'available_periods' => [],
            'unavailable_periods' => [],
        ];
    }

    private function formatTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    private function doctorProfileId(int $clinicId, int $doctorUserId): ?int
    {
        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->where('clinic_id', $clinicId)
            ->where('user_id', $doctorUserId)
            ->value('id');
    }
}
