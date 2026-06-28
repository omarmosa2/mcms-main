<?php

namespace App\Services;

use App\Models\ClinicWorkingHour;
use Illuminate\Support\Carbon;

class ClinicWorkingHoursService
{
    /**
     * @param  array<int, array<string, mixed>>  $workingHours
     */
    public function replaceForClinic(int $clinicId, array $workingHours): void
    {
        foreach ($this->normalizeRows($workingHours) as $row) {
            ClinicWorkingHour::query()->updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'day_of_week' => $row['day_of_week'],
                ],
                [
                    'is_active' => $row['is_active'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                ],
            );
        }
    }

    /**
     * @return array<int, array{day_of_week: int, is_active: bool, start_time: ?string, end_time: ?string}>
     */
    public function getForClinic(int $clinicId): array
    {
        $rows = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->get()
            ->keyBy('day_of_week');

        return collect(ClinicWorkingHour::DAYS)
            ->map(function (int $day) use ($rows): array {
                $row = $rows->get($day);

                return [
                    'day_of_week' => $day,
                    'is_active' => (bool) ($row?->is_active ?? false),
                    'start_time' => $this->formatTime($row?->start_time),
                    'end_time' => $this->formatTime($row?->end_time),
                ];
            })
            ->values()
            ->all();
    }

    public function isAppointmentWithinWorkingHours(
        int $clinicId,
        mixed $scheduledFor,
        int $durationMinutes,
    ): bool {
        $hasSchedule = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->exists();

        if (! $hasSchedule) {
            return false;
        }

        $start = Carbon::parse($scheduledFor);
        $end = $start->copy()->addMinutes($durationMinutes);
        $dayOfWeek = (int) $start->dayOfWeek;

        $workingHour = ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (
            $workingHour === null ||
            ! $workingHour->is_active ||
            $workingHour->start_time === null ||
            $workingHour->end_time === null
        ) {
            return false;
        }

        $workStart = $start->copy()->setTimeFromTimeString($this->formatTime($workingHour->start_time) ?? '00:00');
        $workEnd = $start->copy()->setTimeFromTimeString($this->formatTime($workingHour->end_time) ?? '00:00');

        return $start->greaterThanOrEqualTo($workStart) && $end->lessThanOrEqualTo($workEnd);
    }

    /**
     * @param  array<int, array<string, mixed>>  $workingHours
     * @return array<int, array{day_of_week: int, is_active: bool, start_time: ?string, end_time: ?string}>
     */
    private function normalizeRows(array $workingHours): array
    {
        $inputRows = collect($workingHours)->keyBy('day_of_week');

        return collect(ClinicWorkingHour::DAYS)
            ->map(function (int $day) use ($inputRows): array {
                $input = $inputRows->get($day, []);
                $isActive = filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return [
                    'day_of_week' => $day,
                    'is_active' => $isActive,
                    'start_time' => $isActive ? ($input['start_time'] ?? '09:00') : null,
                    'end_time' => $isActive ? ($input['end_time'] ?? '17:00') : null,
                ];
            })
            ->values()
            ->all();
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 5);
    }
}
