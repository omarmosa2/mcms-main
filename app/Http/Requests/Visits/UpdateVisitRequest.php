<?php

namespace App\Http\Requests\Visits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && ($user->hasPermission('visit.update') || $user->hasPermission('medical.notes.create'));
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $visitId = (int) $this->route('visitId');

        return [
            'queue_entry_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('queue_entries', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
                Rule::unique('visits', 'queue_entry_id')->ignore($visitId),
            ],
            'appointment_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'patient_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'doctor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'visit_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('visits', 'visit_number')
                    ->ignore($visitId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'chief_complaint' => ['sometimes', 'nullable', 'string'],
            'clinical_notes' => ['sometimes', 'nullable', 'string'],
            'diagnosis_notes' => ['sometimes', 'nullable', 'string'],
            'treatment_plan' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
