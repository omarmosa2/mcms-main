<?php

namespace App\Http\Requests\Doctors;

use App\Models\DoctorProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDoctorProfileRequest extends FormRequest
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
        $doctorProfileId = (int) $this->route('doctorProfileId');
        $doctorUserId = DoctorProfile::query()
            ->whereKey($doctorProfileId)
            ->value('user_id');

        return [
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
                Rule::unique('doctor_profiles', 'user_id')
                    ->ignore($doctorProfileId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($doctorUserId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'gender' => ['sometimes', 'required', 'string', Rule::in([DoctorProfile::GENDER_MALE, DoctorProfile::GENDER_FEMALE])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'license_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                Rule::unique('doctor_profiles', 'license_number')
                    ->ignore($doctorProfileId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'specialty' => ['sometimes', 'required', 'string', 'max:150'],
            'consultation_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:480'],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    DoctorProfile::STATUS_ACTIVE,
                    DoctorProfile::STATUS_ON_LEAVE,
                    DoctorProfile::STATUS_INACTIVE,
                ]),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'compensation_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    DoctorProfile::COMPENSATION_PERCENTAGE,
                    DoctorProfile::COMPENSATION_WEEKLY,
                    DoctorProfile::COMPENSATION_MONTHLY,
                ]),
            ],
            'compensation_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'working_hours' => ['sometimes', 'required', 'array', 'size:7'],
            'working_hours.*.day_of_week' => ['required_with:working_hours', 'integer', 'between:0,6', 'distinct'],
            'working_hours.*.is_active' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.end_time' => ['nullable', 'date_format:H:i'],
            'work_schedule' => ['sometimes', 'nullable'],
            'bio' => ['sometimes', 'nullable', 'string'],
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
