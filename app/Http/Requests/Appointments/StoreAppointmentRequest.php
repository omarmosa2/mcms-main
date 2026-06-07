<?php

namespace App\Http\Requests\Appointments;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->clinic_id !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'doctor_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
                function (string $attribute, mixed $value, Closure $fail) use ($clinicId): void {
                    if ($value === null || $clinicId === null) {
                        return;
                    }

                    $doctorExists = User::query()
                        ->where('clinic_id', $clinicId)
                        ->whereKey((int) $value)
                        ->whereHas('roles', function ($query) use ($clinicId): void {
                            $query
                                ->where('roles.clinic_id', $clinicId)
                                ->where('roles.name', 'doctor');
                        })
                        ->exists();

                    if (! $doctorExists) {
                        $fail('The selected doctor must be a doctor in this clinic.');
                    }
                },
            ],
            'appointment_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('appointments', 'appointment_number')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'scheduled_for' => ['required', 'date', 'after_or_equal:now'],
            'duration_minutes' => ['required', 'integer', Rule::in([15, 30, 45, 60])],
            'appointment_type' => ['required', 'string', Rule::in(['first_visit', 'review'])],
            'cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
