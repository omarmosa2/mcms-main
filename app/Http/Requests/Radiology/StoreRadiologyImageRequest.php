<?php

namespace App\Http\Requests\Radiology;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRadiologyImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'mimes:dcm,jpeg,jpg,png,pdf', 'max:10240'],
            'dicom_uid' => ['nullable', 'string', 'max:255'],
            'captured_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
