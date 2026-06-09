<?php

namespace App\Services;

use App\Models\ClinicWorkingHour;
use Illuminate\Support\Carbon;

class ClinicWorkingHoursService
{
    /**
     * @param  array<int, array<string, mixed>>  $workingHours
     */
    public function replaceForDepartment(int $departmentId, array $workingHours): void
    {
        foreach ($this->normalizeRows($workingHours) as $row) {
            ClinicWorkingHour::query()->updateOrCreate(
                [
                    'department_id' => $departmentId,
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

    public function getForDepartment(int $departmentId): array
    {
        $rows = ClinicWorkingHour::query()
            ->where('department_id', $departmentId)
            ->get()
            ->keyBy('day_of_week');

        return collect(ClinicWorkingHour::DAYS)
            ->map(function (string $day) use ($rows): array {
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

    /**
     * @return array<int, array{day_of_week: string, is_active: bool, start_time: ?string, end_time: ?string}>
     */
    public function getForClinic(int $clinicId): array
    {
        $allHours = ClinicWorkingHour::query()
            ->whereHas('department', fn ($query) => $query->where('clinic_id', $clinicId))
            ->get();

        return collect(ClinicWorkingHour::DAYS)
            ->map(function (string $day) use ($allHours): array {
                $dayHours = $allHours
                    ->where('day_of_week', $day)
                    ->where('is_active', true)
                    ->whereNotNull('start_time')
                    ->whereNotNull('end_time');

                if ($dayHours->isEmpty()) {
                    return [
                        'day_of_week' => $day,
                        'is_active' => false,
                        'start_time' => null,
                        'end_time' => null,
                    ];
                }

                $earliestStart = $dayHours->min('start_time');
                $latestEnd = $dayHours->max('end_time');

                return [
                    'day_of_week' => $day,
                    'is_active' => true,
                    'start_time' => $this->formatTime($earliestStart),
                    'end_time' => $this->formatTime($latestEnd),
                ];
            })
            ->values()
            ->all();
    }

    public function isAppointmentWithinWorkingHours(
        int $departmentId,
        mixed $scheduledFor,
        int $durationMinutes,
    ): bool {
        $hasSchedule = ClinicWorkingHour::query()
            ->where('department_id', $departmentId)
            ->exists();

        if (! $hasSchedule) {
            return true;
        }

        $start = Carbon::parse($scheduledFor);
        $end = $start->copy()->addMinutes($durationMinutes);
        $day = strtolower($start->format('l'));

        $workingHour = ClinicWorkingHour::query()
            ->where('department_id', $departmentId)
            ->where('day_of_week', $day)
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
     * @return array<int, array{day_of_week: string, is_active: bool, start_time: ?string, end_time: ?string}>
     */
    private function normalizeRows(array $workingHours): array
    {
        $inputRows = collect($workingHours)->keyBy('day_of_week');

        return collect(ClinicWorkingHour::DAYS)
            ->map(function (string $day) use ($inputRows): array {
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
