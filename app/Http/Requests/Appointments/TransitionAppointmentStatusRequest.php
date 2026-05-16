<?php

namespace App\Http\Requests\Appointments;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->clinic_id !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    Appointment::STATUS_CONFIRMED,
                    Appointment::STATUS_ARRIVED,
                    Appointment::STATUS_COMPLETED,
                    Appointment::STATUS_CANCELED,
                    Appointment::STATUS_NO_SHOW,
                ]),
            ],
            'cancel_reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->input('status') === Appointment::STATUS_CANCELED),
            ],
        ];
    }
}
