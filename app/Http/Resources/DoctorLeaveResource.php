<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorLeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'doctor_id' => $this->doctor_id,
            'doctor' => UserResource::make($this->whenLoaded('doctor')),
            'department_id' => $this->department_id,
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'type' => $this->type,
            'leave_date' => $this->leave_date?->toDateString(),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'reason' => $this->reason,
            'status' => $this->status,
            'appointments_count' => $this->appointmentsCount(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function appointmentsCount(): int
    {
        if ($this->leave_date === null) {
            return 0;
        }

        return Appointment::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('doctor_id', $this->doctor_id)
            ->whereDate('scheduled_for', $this->leave_date->toDateString())
            ->whereNotIn('status', Appointment::TERMINAL_STATUSES)
            ->count();
    }

    private function formatTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 5);
    }
}
