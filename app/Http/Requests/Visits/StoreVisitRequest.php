<?php

namespace App\Http\Requests\Visits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && $user->hasPermission('visit.start');
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'queue_entry_id' => [
                'nullable',
                'integer',
                Rule::exists('queue_entries', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
                Rule::unique('visits', 'queue_entry_id'),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'visit_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('visits', 'visit_number')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'chief_complaint' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'diagnosis_notes' => ['nullable', 'string'],
            'treatment_plan' => ['nullable', 'string'],
        ];
    }
}
