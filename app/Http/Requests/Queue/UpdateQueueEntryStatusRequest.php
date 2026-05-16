<?php

namespace App\Http\Requests\Queue;

use App\Models\QueueEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQueueEntryStatusRequest extends FormRequest
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
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    QueueEntry::STATUS_CALLED,
                    QueueEntry::STATUS_IN_SERVICE,
                    QueueEntry::STATUS_COMPLETED,
                    QueueEntry::STATUS_SKIPPED,
                    QueueEntry::STATUS_CANCELED,
                ]),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
