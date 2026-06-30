<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DispensePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && ($user->hasPermission('pharmacy.prescriptions.dispense') || $user->hasPermission('pharmacy.*'));
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.prescription_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.batch_id' => ['nullable', 'integer'],
        ];
    }
}
