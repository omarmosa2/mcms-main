<?php

namespace App\Http\Requests\Appointments;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
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
        $appointmentId = (int) $this->route('appointmentId');

        return [
            'patient_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'doctor_id' => [
                'sometimes',
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
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('appointments', 'appointment_number')
                    ->ignore($appointmentId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'scheduled_for' => [
                'sometimes',
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $scheduledFor = Carbon::parse($value);
                    $now = now();

                    if ($scheduledFor->isPast()) {
                        $fail('لا يمكن حجز موعد في وقت سابق.');

                        return;
                    }

                    $today = $now->toDateString();
                    $scheduledDate = $scheduledFor->toDateString();

                    if ($scheduledDate < $today) {
                        $fail('لا يمكن حجز موعد في تاريخ سابق.');

                        return;
                    }

                    if ($scheduledDate === $today && $scheduledFor->lte($now)) {
                        $fail('الوقت المختار قد مضى بالفعل.');
                    }
                },
            ],
            'duration_minutes' => ['sometimes', 'required', 'integer', Rule::in([15, 30, 45, 60])],
            'appointment_type' => ['sometimes', 'required', 'string', Rule::in(['first_visit', 'review'])],
            'cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
