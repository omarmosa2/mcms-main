<?php

namespace App\Http\Requests\MedicalRecords;

use App\Models\MedicalRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medical_record.update') ?? false;
    }

    public function rules(): array
    {
        return [
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
        ];
    }
}
