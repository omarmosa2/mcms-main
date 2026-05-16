<?php

namespace App\Http\Requests\Queue;

use App\Models\Appointment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQueueEntryRequest extends FormRequest
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
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'assigned_doctor_id' => [
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
            'queue_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clinicId = $this->user()?->clinic_id;
                $appointmentId = $this->input('appointment_id');
                $patientId = $this->input('patient_id');

                if ($clinicId === null || $appointmentId === null || $patientId === null) {
                    return;
                }

                $appointment = Appointment::query()
                    ->forClinic((int) $clinicId)
                    ->select(['id', 'patient_id'])
                    ->find((int) $appointmentId);

                if ($appointment !== null && (int) $appointment->patient_id !== (int) $patientId) {
                    $validator->errors()->add(
                        'appointment_id',
                        'The selected appointment does not belong to the selected patient.'
                    );
                }
            },
        ];
    }
}
