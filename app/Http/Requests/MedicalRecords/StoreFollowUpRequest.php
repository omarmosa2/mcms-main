<?php

namespace App\Http\Requests\MedicalRecords;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medical_record.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'medical_record_id' => ['nullable', 'integer', 'exists:medical_records,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'follow_up_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recommended_action' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'missed'])],
        ];
    }
}
