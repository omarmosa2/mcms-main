<?php

namespace App\Http\Requests\MedicalRecords;

use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medical_record.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'clinic_type' => ['nullable', 'string', Rule::in(MedicalRecord::CLINIC_TYPES)],
            'form_data' => ['nullable', 'array'],
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'primary_diagnosis' => ['nullable', 'string', 'max:5000'],
            'secondary_diagnosis' => ['nullable', 'string', 'max:5000'],
            'clinical_notes' => ['nullable', 'string', 'max:10000'],
            'examination' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in([
                MedicalRecord::STATUS_DRAFT,
                MedicalRecord::STATUS_ACTIVE,
                MedicalRecord::STATUS_COMPLETED,
                MedicalRecord::STATUS_CANCELLED,
            ])],
            'visit_date' => ['nullable', 'date'],
            'treatment_plans' => ['nullable', 'array'],
            'treatment_plans.*.title' => ['required_with:treatment_plans', 'string', 'max:255'],
            'treatment_plans.*.description' => ['nullable', 'string', 'max:5000'],
            'treatment_plans.*.start_date' => ['nullable', 'date'],
            'treatment_plans.*.end_date' => ['nullable', 'date', 'after_or_equal:treatment_plans.*.start_date'],
            'treatment_plans.*.status' => ['nullable', Rule::in(['new', 'in_progress', 'completed', 'cancelled'])],
            'follow_ups' => ['nullable', 'array'],
            'follow_ups.*.follow_up_date' => ['required_with:follow_ups', 'date'],
            'follow_ups.*.notes' => ['nullable', 'string', 'max:5000'],
            'follow_ups.*.recommended_action' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
