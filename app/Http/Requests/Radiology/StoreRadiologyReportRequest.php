<?php

namespace App\Http\Requests\Radiology;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRadiologyReportRequest extends FormRequest
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
            'findings' => ['required', 'string'],
            'impression' => ['nullable', 'string'],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}
