<?php

namespace App\Http\Resources;

use App\Support\WeekDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'user_id' => $this->user_id,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'work_start_date' => $this->work_start_date?->toDateString(),
            'license_number' => $this->license_number,
            'specialty' => $this->specialty,
            'consultation_duration_minutes' => (int) $this->consultation_duration_minutes,
            'status' => $this->status,
            'compensation_type' => $this->compensation_type,
            'compensation_value' => $this->compensation_value,
            'work_schedule' => $this->work_schedule,
            'bio' => $this->bio,
            'user' => $this->whenLoaded('user', fn () => $this->user !== null ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'is_active' => (bool) $this->user->is_active,
            ] : null),
            'doctor_schedules' => $this->whenLoaded('user', fn () => $this->doctorSchedules()),
            'clinic_working_days' => $this->whenLoaded('clinic', fn () => $this->clinicWorkingDays()),
            'clinic' => $this->whenLoaded('clinic', fn () => $this->clinic !== null ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'code' => $this->clinic->code,
                'is_active' => (bool) $this->clinic->is_active,
                'working_hours' => $this->clinicWorkingDays(),
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, is_available: bool, start_time: string|null, end_time: string|null}>
     */
    private function doctorSchedules(): array
    {
        if (! $this->user?->relationLoaded('doctorSchedules')) {
            return [];
        }

        return $this->user->doctorSchedules
            ->where('clinic_id', $this->clinic_id)
            ->sortBy(fn ($schedule): int => $this->dayOfWeekIndex($schedule))
            ->map(fn ($schedule): array => [
                'day_of_week' => $this->dayOfWeekIndex($schedule),
                'is_available' => (bool) $schedule->is_available,
                'start_time' => $this->formatTime($schedule->start_time),
                'end_time' => $this->formatTime($schedule->end_time),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{day_of_week: int, is_active: bool, start_time: string|null, end_time: string|null}>
     */
    private function clinicWorkingDays(): array
    {
        if (! $this->clinic?->relationLoaded('workingHours')) {
            return [];
        }

        return $this->clinic->workingHours
            ->where('is_active', true)
            ->sortBy(fn ($workingHour): int => $this->dayOfWeekIndex($workingHour))
            ->map(fn ($workingHour): array => [
                'day_of_week' => $this->dayOfWeekIndex($workingHour),
                'is_active' => true,
                'start_time' => $this->formatTime($workingHour->start_time),
                'end_time' => $this->formatTime($workingHour->end_time),
            ])
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

    private function dayOfWeekIndex(object $schedule): int
    {
        return WeekDay::toIndex($schedule->getRawOriginal('day_of_week'));
    }
}
