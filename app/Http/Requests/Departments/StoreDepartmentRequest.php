<?php

namespace App\Http\Requests\Departments;

use App\Models\ClinicWorkingHour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDepartmentRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('departments', 'name')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'working_hours' => ['sometimes', 'array'],
            'working_hours.*.day_of_week' => ['required_with:working_hours', 'string', Rule::in(ClinicWorkingHour::DAYS)],
            'working_hours.*.is_active' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'working_hours.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator): mixed => $this->validateWorkingHours($validator),
        ];
    }

    private function validateWorkingHours(Validator $validator): void
    {
        foreach ((array) $this->input('working_hours', []) as $index => $row) {
            $isActive = filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $startTime = $row['start_time'] ?? null;
            $endTime = $row['end_time'] ?? null;

            if ($isActive) {
                if ($startTime === null || $startTime === '') {
                    $validator->errors()->add("working_hours.$index.start_time", 'وقت بداية الدوام مطلوب.');
                }

                if ($endTime === null || $endTime === '') {
                    $validator->errors()->add("working_hours.$index.end_time", 'وقت نهاية الدوام مطلوب.');
                }

                if ($startTime !== null && $endTime !== null && $endTime <= $startTime) {
                    $validator->errors()->add("working_hours.$index.end_time", 'وقت نهاية الدوام يجب أن يكون بعد وقت البداية.');
                }

                continue;
            }

            if ($startTime !== null || $endTime !== null) {
                $validator->errors()->add("working_hours.$index.start_time", 'الأيام غير المفعلة لا تقبل أوقات دوام.');
            }
        }
    }
}
