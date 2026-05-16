<?php

namespace App\Http\Requests\DoctorSchedules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorScheduleRequest extends FormRequest
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
        $scheduleId = (int) $this->route('doctorScheduleId');

        return [
            'day_of_week' => ['sometimes', 'required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => [
                'sometimes',
                'required',
                'date_format:H:i',
                Rule::when(
                    fn () => $this->has('start_time'),
                    ['after:start_time'],
                ),
            ],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
