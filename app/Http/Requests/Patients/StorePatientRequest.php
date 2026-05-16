<?php

namespace App\Http\Requests\Patients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
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

        return [
            'file_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('patients', 'file_number')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\s()-]+$/'],
            'notes' => ['nullable', 'string'],
            'chronic_conditions' => ['nullable', 'array'],
            'chronic_conditions.*' => ['nullable', 'string', 'max:191'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['nullable', 'string', 'max:191'],
            'current_medications' => ['nullable', 'array'],
            'current_medications.*' => ['nullable', 'string', 'max:191'],
        ];
    }
}
