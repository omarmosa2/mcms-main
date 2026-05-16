<?php

namespace App\Actions\Financial;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Installment;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ProcessInstallmentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
    ) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $installmentId,
        int $amount,
        ?string $notes = null,
    ): Installment {
        return DB::transaction(function () use ($clinicId, $userId, $installmentId, $amount, $notes): Installment {
            $installment = Installment::query()
                ->forClinic($clinicId)
                ->findOrFail($installmentId);

            if ($installment->status === 'paid') {
                abort(422, 'This installment has already been paid.');
            }

            if ($amount <= 0) {
                abort(422, 'Payment amount must be greater than zero.');
            }

            $newPaidAmount = min($installment->amount, $installment->paid_amount + $amount);
            $installment->paid_amount = $newPaidAmount;

            if ($newPaidAmount >= $installment->amount) {
                $installment->status = 'paid';
                $installment->paid_at = now();
            }

            if ($notes !== null) {
                $installment->notes = $notes;
            }

            $installment->save();

            if ($installment->invoice_id !== null) {
                $invoice = Invoice::query()->find($installment->invoice_id);

                if ($invoice !== null) {
                    $totalPaid = Installment::query()
                        ->where('invoice_id', $invoice->id)
                        ->where('status', 'paid')
                        ->sum('paid_amount');

                    $invoice->paid_amount = (int) $totalPaid;
                    $invoice->balance_amount = max(0, $invoice->total_amount - $invoice->paid_amount);

                    if ($invoice->balance_amount <= 0) {
                        $invoice->status = 'paid';
                    } elseif ($invoice->paid_amount > 0) {
                        $invoice->status = 'partially_paid';
                    }

                    $invoice->save();
                }
            }

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'installment.payment',
                metadata: [
                    'installment_id' => $installmentId,
                    'amount' => $amount,
                    'total_paid' => $installment->paid_amount,
                ],
            );

            return $installment;
        });
    }
}
