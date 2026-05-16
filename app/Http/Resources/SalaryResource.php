<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_salary' => (float) $this->base_salary,
            'allowances' => (float) $this->allowances,
            'deductions' => (float) $this->deductions,
            'net_salary' => (float) $this->net_salary,
            'status' => $this->status,
            'period_month' => $this->period_month,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'paid_at' => $this->paid_at?->toISOString(),
            'payer' => $this->whenLoaded('payer', fn () => [
                'id' => $this->payer->id,
                'name' => $this->payer->name,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
