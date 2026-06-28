<?php

namespace App\Http\Resources;

use App\Models\DoctorProfile;
use App\Support\WeekDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DoctorProfile */
class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'clinic' => $this->whenLoaded('clinic', fn () => [
                'id' => $this->clinic->id,
                'name' => $this->clinic->name,
                'code' => $this->clinic->code,
            ]),
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'specialty' => $this->specialty,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,
            'employment_start_date' => $this->employment_start_date?->toDateString(),
            'compensation_type' => $this->compensation_type,
            'compensation_value' => $this->compensation_value !== null
                ? (float) $this->compensation_value
                : null,
            'percentage_value' => $this->percentage_value !== null
                ? (float) $this->percentage_value
                : null,
            'fixed_weekly_amount' => $this->fixed_weekly_amount !== null
                ? (float) $this->fixed_weekly_amount
                : null,
            'fixed_monthly_amount' => $this->fixed_monthly_amount !== null
                ? (float) $this->fixed_monthly_amount
                : null,
            'currency' => $this->currency ?? 'SYP',
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedulesCollection()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array{id: int, doctor_profile_id: int, clinic_id: int, day_of_week: int, day_name: string, start_time: string, end_time: string, is_available: bool}>
     */
    private function schedulesCollection(): array
    {
        return $this->schedules
            ->sortBy(fn ($schedule): int => (int) $schedule->day_of_week)
            ->map(fn ($schedule): array => [
                'id' => (int) $schedule->id,
                'doctor_profile_id' => (int) $schedule->doctor_profile_id,
                'clinic_id' => (int) $schedule->clinic_id,
                'day_of_week' => (int) $schedule->day_of_week,
                'day_name' => WeekDay::arabicName((string) $schedule->day_of_week),
                'start_time' => $this->formatTime($schedule->start_time),
                'end_time' => $this->formatTime($schedule->end_time),
                'is_available' => (bool) $schedule->is_available,
            ])
            ->values()
            ->all();
    }

    private function formatTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
