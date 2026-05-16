<?php

namespace App\Actions\Billing;

use App\Actions\Accounting\AutoPostAction;
use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\IdempotencyService;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPaymentAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private AutoPostAction $autoPostAction,
        private IdempotencyService $idempotencyService,
        private TransitionInvoiceStatusAction $transitionInvoiceStatusAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $invoiceId, int $userId, array $payload): Payment
    {
        $idempotencyKey = $payload['idempotency_key'] ?? $this->generateIdempotencyKey($clinicId, $invoiceId, $payload);

        return $this->idempotencyService->getOrCreate(
            $idempotencyKey,
            fn () => $this->recordPayment($clinicId, $invoiceId, $userId, $payload),
            $clinicId,
            'payment.record',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordPayment(int $clinicId, int $invoiceId, int $userId, array $payload): Payment
    {
        return DB::transaction(function () use ($clinicId, $invoiceId, $userId, $payload): Payment {
            $invoice = Invoice::query()
                ->forClinic($clinicId)
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status === Invoice::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Draft invoices must be issued before recording payments.',
                ]);
            }

            if ((float) $invoice->balance_amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is already fully paid.',
                ]);
            }

            $amount = round((float) $payload['amount'], 2);

            if ($amount > (float) $invoice->balance_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount exceeds current invoice balance.',
                ]);
            }

            $payment = Payment::query()->create([
                'clinic_id' => $clinicId,
                'invoice_id' => $invoice->id,
                'received_by' => $userId,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'method' => $payload['method'],
                'status' => Payment::STATUS_RECORDED,
                'amount' => $amount,
                'refund_amount' => 0,
                'paid_at' => $payload['paid_at'] ?? now(),
                'refunded_at' => null,
                'notes' => $payload['notes'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]);

            $newPaidAmount = round((float) $invoice->paid_amount + $amount, 2);
            $newBalanceAmount = round(max(0, (float) $invoice->total_amount - $newPaidAmount), 2);
            $newInvoiceStatus = $newBalanceAmount <= 0
                ? Invoice::STATUS_PAID
                : Invoice::STATUS_PARTIALLY_PAID;

            $invoice = $this->transitionInvoiceStatusAction->handle(
                clinicId: $clinicId,
                invoiceId: $invoice->id,
                userId: $userId,
                newStatus: $newInvoiceStatus,
                context: ['balance_amount' => $newBalanceAmount],
            );

            $invoice->paid_amount = $newPaidAmount;
            $invoice->save();

            $this->autoPostAction->handlePaymentReceived($payment);

            $this->logAuditAction->handle(
                clinicId: $clinicId,
                userId: $userId,
                action: 'billing.payments.record',
                auditable: $payment,
                newValues: $payment->only([
                    'invoice_id',
                    'method',
                    'status',
                    'amount',
                    'paid_at',
                ]),
                metadata: [
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
    private function generateIdempotencyKey(int $clinicId, int $invoiceId, array $payload): string
    {
        return hash('sha256', "{$clinicId}:{$invoiceId}:{$payload['amount']}:{$payload['method']}");
    }
}
