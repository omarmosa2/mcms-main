<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opening_balance' => (float) $this->opening_balance,
            'total_income' => (float) $this->total_income,
            'total_expenses' => (float) $this->total_expenses,
            'closing_balance' => (float) $this->closing_balance,
            'box_date' => $this->box_date?->toDateString(),
            'status' => $this->status,
            'opener' => $this->whenLoaded('opener', fn () => [
                'id' => $this->opener->id,
                'name' => $this->opener->name,
            ]),
            'opened_at' => $this->opened_at?->toISOString(),
            'closer' => $this->whenLoaded('closer', fn () => [
                'id' => $this->closer->id,
                'name' => $this->closer->name,
            ]),
            'closed_at' => $this->closed_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
