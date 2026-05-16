<?php

namespace App\Http\Requests\Visits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitDiagnosisRequest extends FormRequest
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
        return [
            'icd10_code' => [
                'required',
                'string',
                'max:16',
                'regex:/^[A-TV-Z][0-9][0-9AB](\.[0-9A-TV-Z]{1,4})?$/i',
            ],
            'diagnosis_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'diagnosed_at' => ['nullable', 'date'],
        ];
    }
}
