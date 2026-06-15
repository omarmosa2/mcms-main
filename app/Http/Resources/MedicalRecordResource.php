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
                'chronic_conditions' => $this->patient->relationLoaded('chronicConditions')
                    ? $this->patient->chronicConditions->map(fn ($condition): array => [
                        'id' => $condition->id,
                        'condition' => $condition->condition,
                    ])->values()
                    : [],
                'allergies' => $this->patient->relationLoaded('allergies')
                    ? $this->patient->allergies->map(fn ($allergy): array => [
                        'id' => $allergy->id,
                        'allergy' => $allergy->allergy,
                    ])->values()
                    : [],
                'medications' => $this->patient->relationLoaded('medications')
                    ? $this->patient->medications->map(fn ($medication): array => [
                        'id' => $medication->id,
                        'medication' => $medication->medication,
                    ])->values()
                    : [],
                'attachments' => $this->patient->relationLoaded('attachments')
                    ? PatientAttachmentResource::collection($this->patient->attachments)->resolve()
                    : [],
                'lab_orders' => $this->patient->relationLoaded('labOrders')
                    ? $this->patient->labOrders
                        ->filter(fn ($order): bool => $this->appointment_id === null || $order->visit_id === null || (int) $order->visit_id === (int) $this->appointment_id)
                        ->map(fn ($order): array => $this->formatLabOrder($order))
                        ->values()
                    : [],
                'radiology_orders' => $this->patient->relationLoaded('radiologyOrders')
                    ? $this->patient->radiologyOrders
                        ->filter(fn ($order): bool => $this->appointment_id === null || $order->visit_id === null || (int) $order->visit_id === (int) $this->appointment_id)
                        ->map(fn ($order): array => $this->formatRadiologyOrder($order))
                        ->values()
                    : [],
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
            'prescriptions' => $this->relationLoaded('prescriptions')
                ? $this->prescriptions->map(fn ($prescription): array => [
                    'id' => $prescription->id,
                    'prescription_number' => $prescription->prescription_number,
                    'status' => $prescription->status,
                    'diagnosis' => $prescription->diagnosis,
                    'notes' => $prescription->notes,
                    'issued_at' => $prescription->issued_at?->toISOString(),
                    'dispensed_at' => $prescription->dispensed_at?->toISOString(),
                    'prescriber' => $prescription->relationLoaded('prescriber') && $prescription->prescriber !== null ? [
                        'id' => $prescription->prescriber->id,
                        'name' => $prescription->prescriber->name,
                    ] : null,
                    'items' => $prescription->relationLoaded('items')
                        ? $prescription->items->map(fn ($item): array => [
                            'id' => $item->id,
                            'medication_name' => $item->medication_name,
                            'dosage' => $item->dosage,
                            'frequency' => $item->frequency,
                            'duration' => $item->duration,
                            'quantity' => $item->quantity,
                            'instructions' => $item->instructions,
                        ])->values()
                        : [],
                ])->values()
                : [],
            'treatment_plans' => $treatmentPlans,
            'follow_ups' => $followUps,
            'audit_logs' => $this->relationLoaded('auditLogs')
                ? $this->auditLogs->map(fn ($log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'metadata' => $log->metadata,
                    'occurred_at' => $log->occurred_at?->toISOString(),
                    'user' => $log->relationLoaded('user') && $log->user !== null ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                    ] : null,
                ])->values()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function formatLabOrder(mixed $order): array
    {
        return [
            'id' => $order->id,
            'test_code' => $order->test_code,
            'test_name' => $order->test_name,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'notes' => $order->notes,
            'orderer' => $order->relationLoaded('orderer') && $order->orderer !== null ? [
                'id' => $order->orderer->id,
                'name' => $order->orderer->name,
            ] : null,
            'results' => $order->relationLoaded('results')
                ? $order->results->map(fn ($result): array => [
                    'id' => $result->id,
                    'result_value' => $result->result_value,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'notes' => $result->notes,
                    'resulted_at' => $result->resulted_at?->toISOString(),
                ])->values()
                : [],
        ];
    }

    private function formatRadiologyOrder(mixed $order): array
    {
        return [
            'id' => $order->id,
            'study_code' => $order->study_code,
            'study_name' => $order->study_name,
            'modality' => $order->modality,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toISOString(),
            'notes' => $order->notes,
            'orderer' => $order->relationLoaded('orderer') && $order->orderer !== null ? [
                'id' => $order->orderer->id,
                'name' => $order->orderer->name,
            ] : null,
            'reports' => $order->relationLoaded('reports')
                ? $order->reports->map(fn ($report): array => [
                    'id' => $report->id,
                    'report_text' => $report->report_text,
                    'reported_at' => $report->reported_at?->toISOString(),
                ])->values()
                : [],
        ];
    }
}
