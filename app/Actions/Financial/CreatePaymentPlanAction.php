<?php

namespace App\Actions\Financial;

use App\Actions\BaseAction;
use App\Models\PaymentPlan;

class CreatePaymentPlanAction extends BaseAction
{
    public function handle(
        int $clinicId,
        int $createdBy,
        string $name,
        int $installmentCount,
        string $frequency = 'monthly',
        int $minAmount = 0,
        ?string $description = null,
    ): PaymentPlan {
        return PaymentPlan::query()->create([
            'clinic_id' => $clinicId,
            'name' => $name,
            'description' => $description,
            'installment_count' => $installmentCount,
            'frequency' => $frequency,
            'min_amount' => $minAmount,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
    }
}
