<?php

namespace App\Http\Requests;

use App\Models\ClinicWorkingHour;
use App\Models\DoctorProfile;
use App\Models\User;
use App\Support\WeekDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|\Closure|string>>
     */
    public function rules(): array
    {
        $doctor = $this->resolveDoctor();
        $doctorId = $doctor !== null ? (int) $doctor->id : 0;

        return [
            'clinic_id' => ['sometimes', 'required', 'integer', Rule::exists('clinics', 'id')],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'full_name' => ['sometimes', 'required', 'string', 'max:191'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in([DoctorProfile::GENDER_MALE, DoctorProfile::GENDER_FEMALE])],
            'specialty' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:191',
                Rule::unique('doctor_profiles', 'username')->ignore($doctorId),
                Rule::unique('users', 'username')->ignore($doctor?->user_id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'employment_start_date' => ['sometimes', 'nullable', 'date'],
            'compensation_type' => ['sometimes', 'required', 'string', Rule::in([
                DoctorProfile::COMPENSATION_PERCENTAGE,
                DoctorProfile::COMPENSATION_WEEKLY_FIXED,
                DoctorProfile::COMPENSATION_MONTHLY_FIXED,
            ])],
            'compensation_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'schedules' => ['sometimes', 'array'],
            'schedules.*.day_of_week' => ['required_with:schedules', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.is_available' => ['required_with:schedules', 'boolean'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompensationValue($validator);
                $this->validateAccountDetails($validator);
                if ($this->has('schedules')) {
                    $this->validateSchedules($validator);
                }
            },
        ];
    }

    private function validateAccountDetails(Validator $validator): void
    {
        if (! filled($this->input('password'))) {
            return;
        }

        $doctor = $this->resolveDoctor();
        $username = trim((string) $this->input('username', $doctor?->username ?? ''));

        if ($username === '') {
            $validator->errors()->add('username', 'اسم المستخدم مطلوب لإضافة أو تغيير كلمة المرور.');

            return;
        }

        if ($doctor?->user_id === null && User::query()->where('email', mb_strtolower($username).'@doctor.local')->exists()) {
            $validator->errors()->add('username', 'اسم المستخدم مستخدم بالفعل.');
        }
    }

    private function validateCompensationValue(Validator $validator): void
    {
        if ($this->input('compensation_type') === DoctorProfile::COMPENSATION_PERCENTAGE
            && (float) $this->input('compensation_value', 0) > 100) {
            $validator->errors()->add('compensation_value', 'نسبة الطبيب يجب ألا تتجاوز 100%.');
        }
    }

    private function validateSchedules(Validator $validator): void
    {
        $doctor = $this->resolveDoctor();
        $defaultClinicId = $doctor !== null ? (int) $doctor->clinic_id : 0;
        $clinicId = (int) $this->input('clinic_id', $defaultClinicId);
        $clinicWorkingHours = $this->activeClinicWorkingHoursByDay($clinicId);
        $hasClinicWorkingHours = count($clinicWorkingHours) > 0;

        $activeSchedules = collect($this->input('schedules', []))
            ->filter(fn ($schedule): bool => filter_var($schedule['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN));

        if ($activeSchedules->isEmpty()) {
            $validator->errors()->add('schedules', 'يجب تفعيل يوم دوام واحد على الأقل.');
        }

        foreach ($this->input('schedules', []) as $index => $schedule) {
            $isAvailable = filter_var($schedule['is_available'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = $schedule['start_time'] ?? null;
            $endTime = $schedule['end_time'] ?? null;
            $dayOfWeek = isset($schedule['day_of_week']) ? WeekDay::toIndex($schedule['day_of_week']) : null;

            if (! $isAvailable) {
                if (($startTime !== null && $startTime !== '') || ($endTime !== null && $endTime !== '')) {
                    $validator->errors()->add("schedules.{$index}.start_time", 'الأيام غير المفعلة لا تقبل أوقات دوام.');
                }

                continue;
            }

            $clinicWorkingHour = $dayOfWeek !== null ? ($clinicWorkingHours[$dayOfWeek] ?? null) : null;

            if ($hasClinicWorkingHours && $clinicWorkingHour === null) {
                $validator->errors()->add("schedules.{$index}.day_of_week", 'هذا اليوم غير مفعّل ضمن دوام العيادة.');
            }

            if ($startTime === null || $startTime === '') {
                $validator->errors()->add("schedules.{$index}.start_time", 'وقت بداية الدوام مطلوب.');
            }

            if ($endTime === null || $endTime === '') {
                $validator->errors()->add("schedules.{$index}.end_time", 'وقت نهاية الدوام مطلوب.');
            }

            if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
                $validator->errors()->add("schedules.{$index}.end_time", 'وقت نهاية الدوام يجب أن يكون بعد وقت البداية.');
            }

            if (
                $clinicWorkingHour !== null
                && $startTime !== null
                && $endTime !== null
                && ($startTime < $clinicWorkingHour['start_time'] || $endTime > $clinicWorkingHour['end_time'])
            ) {
                $validator->errors()->add(
                    "schedules.{$index}.start_time",
                    "دوام الطبيب يجب أن يكون ضمن دوام العيادة من {$clinicWorkingHour['start_time']} إلى {$clinicWorkingHour['end_time']}.",
                );
            }
        }
    }

    /**
     * @return array<int, array{start_time: string, end_time: string}>
     */
    private function activeClinicWorkingHoursByDay(int $clinicId): array
    {
        if ($clinicId < 1) {
            return [];
        }

        return ClinicWorkingHour::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->mapWithKeys(fn (ClinicWorkingHour $workingHour): array => [
                WeekDay::toIndex($workingHour->getRawOriginal('day_of_week')) => [
                    'start_time' => substr((string) $workingHour->start_time, 0, 5),
                    'end_time' => substr((string) $workingHour->end_time, 0, 5),
                ],
            ])
            ->all();
    }

    private function resolveDoctor(): ?DoctorProfile
    {
        $doctorId = $this->route('doctor');

        if ($doctorId === null) {
            return null;
        }

        return DoctorProfile::query()
            ->withoutGlobalScope('clinic')
            ->find((int) $doctorId);
    }
}
