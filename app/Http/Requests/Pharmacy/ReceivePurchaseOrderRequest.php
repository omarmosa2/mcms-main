<?php

namespace App\Http\Requests\Pharmacy;

use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->clinic_id !== null
            && ($user->hasPermission('billing.generate') || $user->hasPermission('payment.record'));
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $clinicId = $this->user()?->clinic_id;

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('purchase_order_items', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one purchase order item is required for receiving.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            /** @var array<int, array{item_id?: mixed, quantity_received?: mixed}> $items */
            foreach ($items as $index => $item) {
                $itemId = isset($item['item_id']) ? (int) $item['item_id'] : 0;
                $quantityReceived = isset($item['quantity_received']) ? (int) $item['quantity_received'] : 0;

                if ($itemId <= 0 || $quantityReceived <= 0) {
                    continue;
                }

                $purchaseOrderItem = PurchaseOrderItem::query()->find($itemId);

                if ($purchaseOrderItem === null) {
                    continue;
                }

                $remaining = (int) $purchaseOrderItem->quantity_ordered - (int) $purchaseOrderItem->quantity_received;

                if ($quantityReceived > $remaining) {
                    $validator->errors()->add(
                        "items.$index.quantity_received",
                        sprintf('Quantity received exceeds remaining quantity (%d).', max($remaining, 0)),
                    );
                }
            }
        });
    }
}
