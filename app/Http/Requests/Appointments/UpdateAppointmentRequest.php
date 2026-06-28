<?php

namespace App\Http\Requests\Appointments;

use App\Models\DoctorProfile;
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
            'clinic_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('clinics', 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'patient_id' => [
                'sometimes',
                'required',
                'integer',
                // المرضى دائماً مرتبطون بـ clinic_id الخاص بالمستخدم (العيادة الأم)
                // وليس بالعيادة الفرعية المُختارة في الفورم
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'doctor_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($clinicId): void {
                    $targetClinicId = $this->targetClinicId($clinicId);

                    if ($value === null || $targetClinicId === null) {
                        return;
                    }

                    $doctorExists = DoctorProfile::query()
                        ->withoutGlobalScope('clinic')
                        ->where('clinic_id', $targetClinicId)
                        ->where('user_id', (int) $value)
                        ->where('is_active', true)
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
                    ->where(fn ($query) => $query->where('clinic_id', $this->targetClinicId($clinicId))),
            ],
            'scheduled_for' => [
                'sometimes',
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $scheduledFor = Carbon::parse($value);
                    $now = now();
                    $today = $now->toDateString();
                    $scheduledDate = $scheduledFor->toDateString();

                    if ($scheduledDate < $today) {
                        $fail('يمكن تعديل المواعيد لليوم الحالي فقط.');

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

    private function targetClinicId(?int $fallbackClinicId): ?int
    {
        $selectedClinicId = $this->integer('clinic_id');

        return $selectedClinicId > 0 ? $selectedClinicId : $fallbackClinicId;
    }
}
