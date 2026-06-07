<?php

namespace App\Http\Requests\Lab;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabOrderRequest extends FormRequest
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
            'test_code' => ['nullable', 'string', 'max:50'],
            'test_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
