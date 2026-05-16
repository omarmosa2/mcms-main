<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'invoice_id' => $this->invoice_id,
            'received_by' => $this->received_by,
            'payment_reference' => $this->payment_reference,
            'method' => $this->method,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'refund_amount' => (float) $this->refund_amount,
            'paid_at' => $this->paid_at?->toISOString(),
            'refunded_at' => $this->refunded_at?->toISOString(),
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice?->id,
                'invoice_number' => $this->invoice?->invoice_number,
                'status' => $this->invoice?->status,
                'total_amount' => (float) $this->invoice?->total_amount,
                'paid_amount' => (float) $this->invoice?->paid_amount,
                'balance_amount' => (float) $this->invoice?->balance_amount,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
