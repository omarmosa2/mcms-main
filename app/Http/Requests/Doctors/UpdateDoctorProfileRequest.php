<?php

namespace App\Http\Requests\Doctors;

use App\Models\DoctorProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        return [
            'user_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
                Rule::unique('doctor_profiles', 'user_id')
                    ->ignore($doctorProfileId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
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
            'consultation_duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    DoctorProfile::STATUS_ACTIVE,
                    DoctorProfile::STATUS_ON_LEAVE,
                    DoctorProfile::STATUS_INACTIVE,
                ]),
            ],
            'work_schedule' => [
                'sometimes',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $this->validateWorkSchedule($value, $fail);
                },
            ],
            'bio' => ['sometimes', 'nullable', 'string'],
        ];
    }

    private function validateWorkSchedule(mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_array($value)) {
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return;
            }
        }

        $fail('The work schedule must be a valid JSON object or array.');
    }
}
