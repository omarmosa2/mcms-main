<?php

namespace App\Http\Requests\Visits;

use App\Models\Visit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionVisitStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || $user->clinic_id === null) {
            return false;
        }

        $requestedStatus = (string) $this->input('status');

        if ($requestedStatus === Visit::STATUS_COMPLETED) {
            return $user->hasPermission('visit.complete');
        }

        if ($requestedStatus === Visit::STATUS_IN_PROGRESS) {
            return $user->hasPermission('visit.update') || $user->hasPermission('visit.start');
        }

        return false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    Visit::STATUS_IN_PROGRESS,
                    Visit::STATUS_COMPLETED,
                ]),
            ],
        ];
    }
}
