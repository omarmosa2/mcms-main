<?php

namespace App\Actions\Billing;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Invoice;

class ShowInvoiceAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(int $clinicId, int $invoiceId, int $userId): Invoice
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->with([
                'patient:id,clinic_id,first_name,last_name',
                'visit:id,clinic_id,visit_number,status',
                'appointment:id,clinic_id,appointment_number,status',
                'items',
                'payments',
            ])
            ->findOrFail($invoiceId);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'billing.invoices.show',
            auditable: $invoice,
        );

        return $invoice;
    }
}
