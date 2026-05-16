<?php

namespace App\Http\Requests\Visits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitVitalSignRequest extends FormRequest
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
            'systolic_bp' => ['nullable', 'integer', 'between:50,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:30,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,260'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'temperature_celsius' => ['nullable', 'numeric', 'between:30,45'],
            'weight_kg' => ['nullable', 'numeric', 'between:0.5,500'],
            'height_cm' => ['nullable', 'numeric', 'between:20,300'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
