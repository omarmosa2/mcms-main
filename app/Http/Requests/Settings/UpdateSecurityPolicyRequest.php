<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSecurityPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->clinic_id !== null
            && $this->user()->isClinicSecurityManager();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password_min_length' => ['required', 'integer', 'min:8', 'max:32'],
            'require_mixed_case' => ['required', 'boolean'],
            'require_numbers' => ['required', 'boolean'],
            'require_symbols' => ['required', 'boolean'],
            'session_lifetime_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'idle_timeout_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'force_two_factor' => ['required', 'boolean'],
            'confirm_password_for_security_actions' => ['required', 'boolean'],
            'audit_retention_days' => ['required', 'integer', 'min:30', 'max:2555'],
            'sensitive_access_retention_days' => ['required', 'integer', 'min:30', 'max:2555'],
        ];
    }
}
