<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\IdempotencyService;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundPaymentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private IdempotencyService $idempotencyService,
        private TransitionInvoiceStatusAction $transitionInvoiceStatusAction,
        private TransitionPaymentStatusAction $transitionPaymentStatusAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $paymentId, int $userId, array $payload): Payment
    {
        $idempotencyKey = $payload['idempotency_key'] ?? $this->generateIdempotencyKey($clinicId, $paymentId, $payload);

        return $this->idempotencyService->getOrCreate(
            $idempotencyKey,
            fn () => $this->refundPayment($clinicId, $paymentId, $userId, $payload),
            $clinicId,
            'payment.refund',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function refundPayment(int $clinicId, int $paymentId, int $userId, array $payload): Payment
    {
        return DB::transaction(function () use ($clinicId, $paymentId, $userId, $payload): Payment {
            $payment = Payment::query()
                ->forClinic($clinicId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = Invoice::query()
                ->forClinic($clinicId)
                ->whereKey($payment->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === Payment::STATUS_VOIDED) {
                throw ValidationException::withMessages([
                    'payment' => 'Voided payments cannot be refunded.',
                ]);
            }

            $refundAmount = round((float) $payload['amount'], 2);
            $availableRefund = round((float) $payment->amount - (float) $payment->refund_amount, 2);

            if ($refundAmount > $availableRefund) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds available refundable amount.',
                ]);
            }

            $newRefundAmount = round((float) $payment->refund_amount + $refundAmount, 2);
            $newPaymentStatus = $newRefundAmount > 0 ? Payment::STATUS_REFUNDED : Payment::STATUS_RECORDED;

            $payment = $this->transitionPaymentStatusAction->handle(
                clinicId: $clinicId,
                paymentId: $payment->id,
                userId: $userId,
                newStatus: $newPaymentStatus,
                context: ['refund_amount' => $newRefundAmount],
            );

            if (array_key_exists('notes', $payload)) {
                $payment->notes = $payload['notes'];
                $payment->save();
            }

            $newPaidAmount = round(max(0, (float) $invoice->paid_amount - $refundAmount), 2);
            $newBalanceAmount = round(max(0, (float) $invoice->total_amount - $newPaidAmount), 2);

            if ($newPaidAmount <= 0) {
                $newInvoiceStatus = Invoice::STATUS_ISSUED;
            } elseif ($newBalanceAmount <= 0) {
                $newInvoiceStatus = Invoice::STATUS_PAID;
            } else {
                $newInvoiceStatus = Invoice::STATUS_PARTIALLY_PAID;
            }

            $invoice = $this->transitionInvoiceStatusAction->handle(
                clinicId: $clinicId,
                invoiceId: $invoice->id,
                userId: $userId,
                newStatus: $newInvoiceStatus,
                context: ['balance_amount' => $newBalanceAmount],
            );

            $invoice->paid_amount = $newPaidAmount;
            $invoice->save();

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'billing.payments.refund',
                auditable: $payment,
                newValues: $payment->only([
                    'status',
                    'refund_amount',
                    'refunded_at',
                ]),
                metadata: [
                    'refund_amount' => $refundAmount,
                    'invoice_status_after' => $invoice->status,
                    'invoice_balance_after' => $invoice->balance_amount,
                ],
            );

            return $payment->fresh(['invoice']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateIdempotencyKey(int $clinicId, int $paymentId, array $payload): string
    {
        return hash('sha256', "{$clinicId}:{$paymentId}:{$payload['amount']}:refund");
    }
}
