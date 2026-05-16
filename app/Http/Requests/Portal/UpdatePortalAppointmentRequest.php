<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortalAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['reschedule', 'cancel'])],
            'scheduled_for' => ['required_if:action,reschedule', 'nullable', 'date', 'after:now'],
            'cancel_reason' => ['required_if:action,cancel', 'nullable', 'string', 'max:255'],
        ];
    }
}
