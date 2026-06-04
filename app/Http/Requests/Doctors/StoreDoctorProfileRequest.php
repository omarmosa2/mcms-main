<?php

namespace App\Http\Requests\Doctors;

use App\Models\DoctorProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoctorProfileRequest extends FormRequest
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
        $usesExistingUser = $this->filled('user_id');

        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
                Rule::unique('doctor_profiles', 'user_id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'name' => [$usesExistingUser ? 'nullable' : 'required', 'string', 'max:255'],
            'username' => [$usesExistingUser ? 'nullable' : 'required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => [$usesExistingUser ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'gender' => ['required', 'string', Rule::in([DoctorProfile::GENDER_MALE, DoctorProfile::GENDER_FEMALE])],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('doctor_profiles', 'license_number')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'specialty' => ['required', 'string', 'max:150'],
            'consultation_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    DoctorProfile::STATUS_ACTIVE,
                    DoctorProfile::STATUS_ON_LEAVE,
                    DoctorProfile::STATUS_INACTIVE,
                ]),
            ],
            'compensation_type' => [
                'required',
                'string',
                Rule::in([
                    DoctorProfile::COMPENSATION_PERCENTAGE,
                    DoctorProfile::COMPENSATION_WEEKLY,
                    DoctorProfile::COMPENSATION_MONTHLY,
                ]),
            ],
            'compensation_value' => ['required', 'numeric', 'min:0'],
            'working_hours' => ['required', 'array', 'size:7'],
            'working_hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'working_hours.*.is_active' => ['required', 'boolean'],
            'working_hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.end_time' => ['nullable', 'date_format:H:i'],
            'work_schedule' => ['nullable'],
            'bio' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompensationValue($validator);
                $this->validateWorkingHours($validator);
            },
        ];
    }

    private function validateCompensationValue(Validator $validator): void
    {
        if ($this->input('compensation_type') !== DoctorProfile::COMPENSATION_PERCENTAGE) {
            return;
        }

        if ((float) $this->input('compensation_value', 0) > 100) {
            $validator->errors()->add('compensation_value', 'نسبة الطبيب يجب ألا تتجاوز 100%.');
        }
    }

    private function validateWorkingHours(Validator $validator): void
    {
        foreach ($this->input('working_hours', []) as $index => $day) {
            $isActive = filter_var($day['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = $day['start_time'] ?? null;
            $endTime = $day['end_time'] ?? null;

            if (! $isActive) {
                continue;
            }

            if ($startTime === null || $startTime === '') {
                $validator->errors()->add("working_hours.{$index}.start_time", 'وقت بداية الدوام مطلوب.');
            }

            if ($endTime === null || $endTime === '') {
                $validator->errors()->add("working_hours.{$index}.end_time", 'وقت نهاية الدوام مطلوب.');
            }

            if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
                $validator->errors()->add("working_hours.{$index}.end_time", 'وقت نهاية الدوام يجب أن يكون بعد وقت البداية.');
            }
        }
    }
}
