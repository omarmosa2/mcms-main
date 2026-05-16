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
            'license_number' => $this->license_number,
            'specialty' => $this->specialty,
            'consultation_duration_minutes' => (int) $this->consultation_duration_minutes,
            'status' => $this->status,
            'work_schedule' => $this->work_schedule,
            'bio' => $this->bio,
            'user' => $this->whenLoaded('user', fn () => $this->user !== null ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
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
}
