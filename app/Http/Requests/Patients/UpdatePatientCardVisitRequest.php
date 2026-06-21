<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientCardVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasPermission('medical_record.update') || $user->hasPermission('patient_card.update'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'visit_date' => ['required', 'date'],
            'visit_time' => ['nullable', 'date_format:H:i'],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('clinic_id', $clinicId),
            ],
            'visit_reason' => ['nullable', 'string', 'max:5000'],
            'chief_complaint' => ['nullable', 'string', 'max:5000'],
            'general_notes' => ['nullable', 'string', 'max:5000'],
            'new_symptoms' => ['nullable', 'string', 'max:5000'],
            'medical_or_surgical_complaint' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'prescribed_treatment_or_referral' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
