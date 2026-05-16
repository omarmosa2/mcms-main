<?php

namespace App\Http\Requests\DoctorSchedules;

use App\Models\User;
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
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
                function (string $attribute, mixed $value, $fail) use ($clinicId): void {
                    if ($clinicId === null) {
                        return;
                    }

                    $isDoctor = User::query()
                        ->where('clinic_id', $clinicId)
                        ->whereKey((int) $value)
                        ->whereHas('roles', function ($query) use ($clinicId): void {
                            $query
                                ->where('roles.clinic_id', $clinicId)
                                ->where('roles.name', 'doctor');
                        })
                        ->exists();

                    if (! $isDoctor) {
                        $fail('The selected user must be a doctor in this clinic.');
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
