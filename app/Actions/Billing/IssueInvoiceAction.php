<?php

namespace App\Actions\Billing;

use App\Actions\Accounting\AutoPostAction;
use App\Actions\BaseAction;
use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class IssueInvoiceAction extends BaseAction
{
    public function __construct(
        private AutoPostAction $autoPostAction,
        private TransitionInvoiceStatusAction $transitionAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(int $clinicId, int $invoiceId, int $userId, array $payload = []): Invoice
    {
        $invoice = Invoice::query()
            ->forClinic($clinicId)
            ->withCount('items')
            ->findOrFail($invoiceId);

        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be issued.',
            ]);
        }

        if ((int) $invoice->items_count === 0) {
            throw ValidationException::withMessages([
                'items' => 'Invoice must contain at least one line item before issuing.',
            ]);
        }

        $balanceAmount = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);

        $invoice = $this->transitionAction->handle(
            clinicId: $clinicId,
            invoiceId: $invoiceId,
            userId: $userId,
            newStatus: Invoice::STATUS_ISSUED,
            context: ['balance_amount' => $balanceAmount],
        );

        if (array_key_exists('due_at', $payload)) {
            $invoice->due_at = $payload['due_at'];
            $invoice->save();
        }

        if (array_key_exists('notes', $payload)) {
            $invoice->notes = $payload['notes'];
            $invoice->save();
        }

        $this->autoPostAction->handleInvoiceIssued($invoice, new Collection);

        return $invoice->fresh(['items', 'payments', 'patient']);
    }
}
