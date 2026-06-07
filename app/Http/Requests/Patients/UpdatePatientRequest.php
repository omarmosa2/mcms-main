<?php

namespace App\Http\Requests\Patients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->clinic_id !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $patientId = (int) $this->route('patientId');

        return [
            'file_number' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::unique('patients', 'file_number')
                    ->ignore($patientId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['sometimes', 'nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'chronic_conditions' => ['sometimes', 'nullable', 'array'],
            'chronic_conditions.*' => ['nullable', 'string', 'max:191'],
            'allergies' => ['sometimes', 'nullable', 'array'],
            'allergies.*' => ['nullable', 'string', 'max:191'],
            'current_medications' => ['sometimes', 'nullable', 'array'],
            'current_medications.*' => ['nullable', 'string', 'max:191'],
        ];
    }
}
