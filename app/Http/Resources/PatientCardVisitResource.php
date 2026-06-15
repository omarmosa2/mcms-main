<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientCardVisitResource extends JsonResource
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
            'appointment_id' => $this->appointment_id,
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'clinic_type' => $this->department->clinic_type,
            ]),
            'visit_date' => $this->visit_date?->toDateString(),
            'visit_time' => $this->visit_time?->format('H:i'),
            'visit_reason' => $this->visit_reason,
            'chief_complaint' => $this->chief_complaint,
            'general_notes' => $this->general_notes,
            'new_symptoms' => $this->new_symptoms,
            'medical_or_surgical_complaint' => $this->medical_or_surgical_complaint,
            'diagnosis' => $this->diagnosis,
            'prescribed_treatment_or_referral' => $this->prescribed_treatment_or_referral,
            'signature' => $this->signature,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
