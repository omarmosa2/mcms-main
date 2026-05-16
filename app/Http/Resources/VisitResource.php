<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
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
            'queue_entry_id' => $this->queue_entry_id,
            'appointment_id' => $this->appointment_id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'visit_number' => $this->visit_number,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'in_progress_at' => $this->in_progress_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'chief_complaint' => $this->chief_complaint,
            'clinical_notes' => $this->clinical_notes,
            'diagnosis_notes' => $this->diagnosis_notes,
            'treatment_plan' => $this->treatment_plan,
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
            'appointment' => $this->whenLoaded('appointment', fn () => [
                'id' => $this->appointment?->id,
                'appointment_number' => $this->appointment?->appointment_number,
                'status' => $this->appointment?->status,
            ]),
            'queue_entry' => $this->whenLoaded('queueEntry', fn () => [
                'id' => $this->queueEntry?->id,
                'queue_date' => $this->queueEntry?->queue_date?->toDateString(),
                'queue_number' => $this->queueEntry?->queue_number,
                'status' => $this->queueEntry?->status,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
