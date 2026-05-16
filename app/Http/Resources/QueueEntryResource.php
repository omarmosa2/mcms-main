<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueEntryResource extends JsonResource
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
            'appointment_id' => $this->appointment_id,
            'patient_id' => $this->patient_id,
            'assigned_doctor_id' => $this->assigned_doctor_id,
            'called_by' => $this->called_by,
            'queue_date' => $this->queue_date?->toDateString(),
            'queue_number' => $this->queue_number,
            'priority' => $this->priority,
            'status' => $this->status,
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'called_at' => $this->called_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'first_name' => $this->patient?->first_name,
                'last_name' => $this->patient?->last_name,
                'full_name' => trim("{$this->patient?->first_name} {$this->patient?->last_name}"),
            ]),
            'appointment' => $this->whenLoaded('appointment', fn () => [
                'id' => $this->appointment?->id,
                'appointment_number' => $this->appointment?->appointment_number,
            ]),
            'assigned_doctor' => $this->whenLoaded('assignedDoctor', fn () => [
                'id' => $this->assignedDoctor?->id,
                'name' => $this->assignedDoctor?->name,
            ]),
            'called_by_user' => $this->whenLoaded('calledBy', fn () => [
                'id' => $this->calledBy?->id,
                'name' => $this->calledBy?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
