<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class TransitionPaymentStatusAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(int $clinicId, int $paymentId, int $userId, string $newStatus, array $context = []): Payment
    {
        $payment = Payment::query()
            ->forClinic($clinicId)
            ->findOrFail($paymentId);

        $currentStatus = $payment->status;

        if (! in_array($newStatus, Payment::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid payment status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $payment->only(['status', 'refund_amount', 'refunded_at', 'voided_at', 'voided_by']);

        $payment->status = $newStatus;

        if ($newStatus === Payment::STATUS_REFUNDED) {
            $payment->refunded_at = now();
        }

        if ($newStatus === Payment::STATUS_VOIDED) {
            $payment->voided_at = now();
            $payment->voided_by = $userId;
        }

        if (array_key_exists('refund_amount', $context)) {
            $payment->refund_amount = round((float) $context['refund_amount'], 2);
        }

        $payment->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'billing.payments.transition_status',
            auditable: $payment,
            oldValues: $oldValues,
            newValues: $payment->only(['status', 'refund_amount', 'refunded_at', 'voided_at', 'voided_by']),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $payment->fresh();
    }
}
