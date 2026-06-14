<?php

namespace App\Http\Resources;

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
            'department_id' => $this->department_id,
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
            'working_hours' => $this->whenLoaded('user', fn () => $this->formatWorkingHours()),
            'department' => $this->whenLoaded('department', fn () => $this->department !== null ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
                'is_active' => (bool) $this->department->is_active,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, is_active: bool, start_time: string|null, end_time: string|null}>
     */
    private function formatWorkingHours(): array
    {
        $schedules = $this->user?->relationLoaded('doctorSchedules')
            ? $this->user->doctorSchedules->keyBy('day_of_week')
            : collect();

        return collect([6, 0, 1, 2, 3, 4, 5])
            ->map(function (int $dayOfWeek) use ($schedules): array {
                $schedule = $schedules->get($dayOfWeek);

                return [
                    'day_of_week' => $dayOfWeek,
                    'is_active' => $schedule !== null && (bool) $schedule->is_available,
                    'start_time' => $schedule?->start_time,
                    'end_time' => $schedule?->end_time,
                ];
            })
            ->values()
            ->all();
    }
}
