<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && $user->hasPermission('billing.generate');
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;
        $invoiceId = (int) $this->route('invoiceId');

        return [
            'patient_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('patients', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'appointment_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId),
                ),
            ],
            'invoice_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('invoices', 'invoice_number')
                    ->ignore($invoiceId)
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.service_code' => ['nullable', 'string', 'max:50'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
