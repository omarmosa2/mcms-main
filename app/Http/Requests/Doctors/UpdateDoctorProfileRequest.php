<?php

namespace App\Http\Requests\Doctors;

use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Support\WeekDay;
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
        $doctorProfile = DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->select(['id', 'user_id'])
            ->find($doctorProfileId);
        $doctorUserId = $doctorProfile?->user_id;

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
                Rule::unique('users', 'email')->ignore($doctorUserId, 'id'),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'clinic_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('clinics', 'id'),
            ],
            'gender' => ['sometimes', 'required', 'string', Rule::in([DoctorProfile::GENDER_MALE, DoctorProfile::GENDER_FEMALE])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'work_start_date' => ['sometimes', 'nullable', 'date'],
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
            'working_hours' => ['sometimes', 'array'],
            'working_hours.*.day_of_week' => ['required_with:working_hours', Rule::in([...WeekDay::DAYS, 0, 1, 2, 3, 4, 5, 6, '0', '1', '2', '3', '4', '5', '6']), 'distinct'],
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
        $clinicWorkingHours = $this->activeClinicWorkingHoursByDoctorDay();
        $hasClinicWorkingHours = $this->hasClinicWorkingHoursConfigured();

        foreach ($this->input('working_hours', []) as $index => $day) {
            $isActive = filter_var($day['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = $day['start_time'] ?? null;
            $endTime = $day['end_time'] ?? null;
            $dayOfWeek = isset($day['day_of_week'])
                ? WeekDay::normalize($day['day_of_week'])
                : null;

            if (! $isActive) {
                if ($startTime !== null || $endTime !== null) {
                    $validator->errors()->add("working_hours.{$index}.start_time", 'الأيام غير المفعلة لا تقبل أوقات دوام.');
                }

                continue;
            }

            $clinicWorkingHour = $dayOfWeek !== null ? ($clinicWorkingHours[$dayOfWeek] ?? null) : null;

            if ($hasClinicWorkingHours && $clinicWorkingHour === null) {
                $validator->errors()->add("working_hours.{$index}.day_of_week", 'هذا اليوم خارج دوام العيادة.');
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

            if (
                $clinicWorkingHour !== null &&
                $startTime !== null &&
                $endTime !== null &&
                ($startTime < $clinicWorkingHour['start_time'] || $endTime > $clinicWorkingHour['end_time'])
            ) {
                $validator->errors()->add(
                    "working_hours.{$index}.start_time",
                    "دوام الطبيب يجب أن يكون ضمن دوام العيادة من {$clinicWorkingHour['start_time']} إلى {$clinicWorkingHour['end_time']}.",
                );
            }
        }
    }

    /**
     * @return array<int, array{start_time: string, end_time: string}>
     */
    private function activeClinicWorkingHoursByDoctorDay(): array
    {
        $clinicId = $this->input('clinic_id', $this->user()?->clinic_id);

        if ($clinicId === null) {
            return [];
        }

        return ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->mapWithKeys(fn (ClinicWorkingHour $workingHour): array => [
                WeekDay::normalize($workingHour->day_of_week) => [
                    'start_time' => $this->formatTime($workingHour->start_time),
                    'end_time' => $this->formatTime($workingHour->end_time),
                ],
            ])
            ->all();
    }

    private function hasClinicWorkingHoursConfigured(): bool
    {
        $clinicId = $this->input('clinic_id', $this->user()?->clinic_id);

        if ($clinicId === null) {
            return false;
        }

        return ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->exists();
    }

    private function formatTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
