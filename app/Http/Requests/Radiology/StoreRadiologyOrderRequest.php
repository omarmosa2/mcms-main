<?php

namespace App\Http\Requests\Radiology;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRadiologyOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && ($user->hasPermission('visit.update') || $user->hasPermission('medical.notes.create'));
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'study_code' => ['nullable', 'string', 'max:50'],
            'study_name' => ['required', 'string', 'max:255'],
            'modality' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
