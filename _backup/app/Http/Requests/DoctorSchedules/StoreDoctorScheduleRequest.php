<?php

namespace App\Http\Requests\DoctorSchedules;

use App\Models\DoctorProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->clinic_id !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctor_profiles', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
                function (string $attribute, mixed $value, $fail) use ($clinicId): void {
                    if ($clinicId === null) {
                        return;
                    }

                    $isDoctor = DoctorProfile::query()
                        ->withoutGlobalScope('clinic')
                        ->where('clinic_id', $clinicId)
                        ->whereKey((int) $value)
                        ->exists();

                    if (! $isDoctor) {
                        $fail('The selected doctor profile must belong to this clinic.');
                    }
                },
            ],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
