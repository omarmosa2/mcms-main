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
            'name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'clinic_id' => $this->clinic_id,
            'user_id' => $this->user_id,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'employment_start_date' => $this->work_start_date?->toDateString(),
            'license_number' => $this->license_number,
            'specialty' => $this->specialty,
            'consultation_duration_minutes' => (int) $this->consultation_duration_minutes,
            'status' => $this->status,
            'is_active' => $this->whenLoaded('user', fn () => (bool) $this->user?->is_active),
            'email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'username' => $this->whenLoaded('user', fn () => $this->user?->email),
            'compensation_type' => $this->compensation_type,
            'compensation_value' => $this->compensation_value,
            'work_schedule' => $this->work_schedule,
            'bio' => $this->bio,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->doctorSchedules()),
            'clinic' => $this->whenLoaded('clinic', fn () => $this->clinic !== null ? [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'code' => $this->clinic->code,
                'is_active' => (bool) $this->clinic->is_active,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id: int, clinic_id: int, doctor_id: int, day_of_week: int, day_name: string, start_time: string|null, end_time: string|null, is_available: bool}>
     */
    private function doctorSchedules(): array
    {
        if (! $this->relationLoaded('schedules')) {
            return [];
        }

        return $this->schedules
            ->where('clinic_id', $this->clinic_id)
            ->sortBy(fn ($schedule): int => $this->dayOfWeekIndex($schedule))
            ->map(fn ($schedule): array => [
                'id' => (int) $schedule->id,
                'clinic_id' => (int) $schedule->clinic_id,
                'doctor_id' => (int) $schedule->doctor_id,
                'day_of_week' => $this->dayOfWeekIndex($schedule),
                'day_name' => WeekDay::arabicName((string) $this->dayOfWeekIndex($schedule)),
                'start_time' => $this->formatTime($schedule->start_time),
                'end_time' => $this->formatTime($schedule->end_time),
                'is_available' => (bool) $schedule->is_available,
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
