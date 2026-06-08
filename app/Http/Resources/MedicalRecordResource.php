<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $treatmentPlans = $this->relationLoaded('treatmentPlans')
            ? TreatmentPlanResource::collection($this->treatmentPlans)->resolve()
            : [];

        $followUps = $this->relationLoaded('followUps')
            ? FollowUpResource::collection($this->followUps)->resolve()
            : [];

        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'patient_id' => $this->patient_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'full_name' => trim("{$this->patient->first_name} {$this->patient->last_name}"),
                'file_number' => $this->patient->file_number,
                'phone' => $this->patient->phone,
                'date_of_birth' => $this->patient->date_of_birth?->toDateString(),
                'gender' => $this->patient->gender,
            ]),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'clinic_type' => $this->department->clinic_type,
            ]),
            'appointment_id' => $this->appointment_id,
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'record_number' => $this->record_number,
            'clinic_type' => $this->clinic_type,
            'form_data' => $this->form_data,
            'chief_complaint' => $this->chief_complaint,
            'primary_diagnosis' => $this->primary_diagnosis,
            'secondary_diagnosis' => $this->secondary_diagnosis,
            'clinical_notes' => $this->clinical_notes,
            'examination' => $this->examination,
            'status' => $this->status,
            'visit_date' => $this->visit_date?->toDateString(),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'treatment_plans' => $treatmentPlans,
            'follow_ups' => $followUps,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
