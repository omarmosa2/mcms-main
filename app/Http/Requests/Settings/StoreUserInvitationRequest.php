<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserInvitationRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'role_name' => [
                'required',
                'string',
                Rule::in((array) config('security.invitable_roles')),
            ],
        ];
    }
}
