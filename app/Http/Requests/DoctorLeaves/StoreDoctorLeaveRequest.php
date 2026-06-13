<?php

namespace App\Http\Requests\DoctorLeaves;

use App\Models\DoctorLeave;
use App\Models\DoctorProfile;
use App\Models\DoctorSchedule;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDoctorLeaveRequest extends FormRequest
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
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
                $this->doctorRoleRule($clinicId),
            ],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
                $this->doctorDepartmentRule($clinicId),
            ],
            'type' => ['required', 'string', Rule::in([DoctorLeave::TYPE_FULL_DAY, DoctorLeave::TYPE_HOURLY])],
            'leave_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_if:type,'.DoctorLeave::TYPE_HOURLY, 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:type,'.DoctorLeave::TYPE_HOURLY, 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clinicId = $this->user()?->clinic_id;

                if ($clinicId === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $clinicId = (int) $clinicId;

                $scheduleError = $this->validateDoctorHasSchedule($clinicId);

                if ($scheduleError !== null) {
                    $validator->errors()->add('doctor_id', $scheduleError);

                    return;
                }

                $timeError = $this->validateHourlyLeaveWithinSchedule($clinicId);

                if ($timeError !== null) {
                    $validator->errors()->add('start_time', $timeError);

                    return;
                }

                $conflict = $this->findConflictingLeave($clinicId);

                if ($conflict !== null) {
                    $validator->errors()->add('leave_date', $conflict);
                }
            },
        ];
    }

    private function doctorRoleRule(?int $clinicId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($clinicId): void {
            if ($clinicId === null) {
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
        };
    }

    private function doctorDepartmentRule(?int $clinicId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($clinicId): void {
            if ($clinicId === null || ! $this->filled('doctor_id')) {
                return;
            }

            $matches = DoctorProfile::query()
                ->forClinic((int) $clinicId)
                ->where('user_id', (int) $this->input('doctor_id'))
                ->where('department_id', (int) $value)
                ->exists();

            if (! $matches) {
                $fail('The selected department must match the doctor department.');
            }
        };
    }

    private function validateDoctorHasSchedule(int $clinicId): ?string
    {
        $doctorId = (int) $this->input('doctor_id');
        $dayOfWeek = Carbon::parse((string) $this->input('leave_date'))->dayOfWeek;

        $hasSchedule = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->exists();

        if (! $hasSchedule) {
            return 'لا يمكن تسجيل إجازة لهذا الطبيب، لأنه لا يملك دواماً أساسياً في هذا اليوم.';
        }

        return null;
    }

    private function validateHourlyLeaveWithinSchedule(int $clinicId): ?string
    {
        if ((string) $this->input('type') !== DoctorLeave::TYPE_HOURLY) {
            return null;
        }

        $doctorId = (int) $this->input('doctor_id');
        $dayOfWeek = Carbon::parse((string) $this->input('leave_date'))->dayOfWeek;
        $startTime = (string) $this->input('start_time');
        $endTime = (string) $this->input('end_time');

        $coversLeave = DoctorSchedule::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $endTime)
            ->exists();

        if (! $coversLeave) {
            return 'وقت الإجازة الساعية يجب أن يكون ضمن ساعات دوام الطبيب الأساسية.';
        }

        return null;
    }

    protected function findConflictingLeave(int $clinicId): ?string
    {
        $doctorId = (int) $this->input('doctor_id');
        $date = (string) $this->input('leave_date');
        $type = (string) $this->input('type');

        $query = DoctorLeave::query()
            ->forClinic($clinicId)
            ->where('doctor_id', $doctorId)
            ->whereDate('leave_date', $date)
            ->where('status', DoctorLeave::STATUS_ACTIVE);

        if ((clone $query)->where('type', DoctorLeave::TYPE_FULL_DAY)->exists()) {
            return 'Cannot add another leave while a full-day leave exists for this doctor on the same date.';
        }

        if ($type === DoctorLeave::TYPE_FULL_DAY && (clone $query)->exists()) {
            return 'Cannot add a full-day leave while another leave exists for this doctor on the same date.';
        }

        if ($type !== DoctorLeave::TYPE_HOURLY) {
            return null;
        }

        $overlaps = (clone $query)
            ->where('type', DoctorLeave::TYPE_HOURLY)
            ->where('start_time', '<', (string) $this->input('end_time'))
            ->where('end_time', '>', (string) $this->input('start_time'))
            ->exists();

        return $overlaps ? 'Cannot add overlapping hourly leaves for the same doctor and date.' : null;
    }
}
