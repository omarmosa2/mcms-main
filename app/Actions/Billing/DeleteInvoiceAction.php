<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class DeleteInvoiceAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $invoiceId, int $userId): void
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->withCount('payments')
            ->findOrFail($invoiceId);

        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن حذف إلا الفواتير المسودة.',
            ]);
        }

        if ((int) $invoice->payments_count > 0) {
            throw ValidationException::withMessages([
                'payments' => 'لا يمكن حذف فاتورة عليها مدفوعات.',
            ]);
        }

        $oldValues = $invoice->only([
            'invoice_number',
            'status',
            'total_amount',
            'balance_amount',
        ]);

        $invoice->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'billing.invoices.delete',
            auditable: $invoice,
            oldValues: $oldValues,
        );
    }
}
