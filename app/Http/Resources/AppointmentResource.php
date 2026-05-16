<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'created_by' => $this->created_by,
            'appointment_number' => $this->appointment_number,
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'arrived_at' => $this->arrived_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'canceled_at' => $this->canceled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'first_name' => $this->patient?->first_name,
                'last_name' => $this->patient?->last_name,
                'full_name' => trim("{$this->patient?->first_name} {$this->patient?->last_name}"),
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor?->id,
                'name' => $this->doctor?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
