<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class TransitionInvoiceStatusAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(int $clinicId, int $invoiceId, int $userId, string $newStatus, array $context = []): Invoice
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->findOrFail($invoiceId);

        $currentStatus = $invoice->status;

        if (! in_array($newStatus, Invoice::ALLOWED_TRANSITIONS[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid invoice status transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        $oldValues = $invoice->only(['status', 'issued_at', 'issued_by', 'voided_at', 'voided_by', 'balance_amount']);

        $invoice->status = $newStatus;

        if ($newStatus === Invoice::STATUS_ISSUED) {
            $invoice->issued_at = now();
            $invoice->issued_by = $userId;
        }

        if ($newStatus === Invoice::STATUS_VOID) {
            $invoice->voided_at = now();
            $invoice->voided_by = $userId;
        }

        if (array_key_exists('balance_amount', $context)) {
            $invoice->balance_amount = round((float) $context['balance_amount'], 2);
        }

        $invoice->save();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'billing.invoices.transition_status',
            auditable: $invoice,
            oldValues: $oldValues,
            newValues: $invoice->only(['status', 'issued_at', 'issued_by', 'voided_at', 'voided_by', 'balance_amount']),
            metadata: [
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
            ],
        );

        return $invoice->fresh();
    }
}
