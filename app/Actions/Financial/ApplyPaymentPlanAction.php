<?php

namespace App\Actions\Financial;

use App\Actions\BaseAction;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\PaymentPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ApplyPaymentPlanAction extends BaseAction
{
    public function handle(int $clinicId, int $paymentPlanId, int $invoiceId): array
    {
        return DB::transaction(function () use ($clinicId, $paymentPlanId, $invoiceId): array {
            $paymentPlan = PaymentPlan::query()
                ->forClinic($clinicId)
                ->where('is_active', true)
                ->findOrFail($paymentPlanId);

            $invoice = Invoice::query()
                ->forClinic($clinicId)
                ->findOrFail($invoiceId);

            if ($invoice->total_amount < $paymentPlan->min_amount) {
                abort(422, 'Invoice total is below the minimum amount for this payment plan.');
            }

            $installmentAmount = (int) ceil($invoice->total_amount / $paymentPlan->installment_count);
            $installments = [];

            for ($i = 1; $i <= $paymentPlan->installment_count; $i++) {
                $dueDate = $this->calculateDueDate($paymentPlan->frequency, $i);

                $installment = Installment::query()->create([
                    'clinic_id' => $clinicId,
                    'payment_plan_id' => $paymentPlan->id,
                    'invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'amount' => $installmentAmount,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                    'paid_amount' => 0,
                ]);

                $installments[] = $installment;
            }

            return $installments;
        });
    }

    private function calculateDueDate(string $frequency, int $installmentNumber): CarbonImmutable
    {
        $baseDate = CarbonImmutable::now()->addDay();

        return match ($frequency) {
            'weekly' => $baseDate->addWeeks($installmentNumber),
            'monthly' => $baseDate->addMonths($installmentNumber),
            'quarterly' => $baseDate->addMonths($installmentNumber * 3),
            default => $baseDate->addMonths($installmentNumber),
        };
    }
}
