<?php

namespace App\Http\Requests\DoctorLeaves;

use App\Models\DoctorLeave;
use App\Models\DoctorProfile;
use App\Models\User;
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

                $conflict = $this->findConflictingLeave((int) $clinicId);

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
