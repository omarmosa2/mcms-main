<?php

namespace App\Http\Requests\MedicalRecords;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medical_record.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'medical_record_id' => ['required', 'integer', 'exists:medical_records,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['new', 'in_progress', 'completed', 'cancelled'])],
        ];
    }
}
