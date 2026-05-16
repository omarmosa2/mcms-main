<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Salary;
use Illuminate\Support\Collection;

class AutoPostAction extends BaseAction
{
    public function __construct(
        private PostJournalEntryAction $postJournalEntry,
    ) {}

    public function handleInvoiceIssued(Invoice $invoice, Collection $lines): void
    {
        $clinicId = $invoice->clinic_id;
        $userId = $invoice->issued_by ?? 1;

        $account4100 = $this->getRevenueAccount($clinicId, '4100');
        $account1200 = $this->getReceivableAccount($clinicId, '1200');

        if ($account4100 === null || $account1200 === null) {
            return;
        }

        $journalLines = [
            [
                'account_id' => $account1200->id,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0,
                'notes' => 'Invoice: '.$invoice->invoice_number,
            ],
            [
                'account_id' => $account4100->id,
                'debit' => 0,
                'credit' => (float) $invoice->total_amount,
                'notes' => 'Invoice: '.$invoice->invoice_number,
            ],
        ];

        $this->postJournalEntry->handle(
            clinicId: $clinicId,
            userId: $userId,
            lines: $journalLines,
            description: 'Invoice issued: '.$invoice->invoice_number,
            referenceType: Invoice::class,
            referenceId: $invoice->id,
            entryDate: $invoice->issued_at?->toDateString(),
        );
    }

    public function handlePaymentReceived(Payment $payment): void
    {
        $clinicId = $payment->clinic_id;
        $userId = $payment->received_by ?? 1;
        $invoice = $payment->invoice;

        $account1100 = $this->getCashAccount($clinicId, '1100');
        $account1200 = $this->getReceivableAccount($clinicId, '1200');

        if ($account1100 === null || $account1200 === null) {
            return;
        }

        $journalLines = [
            [
                'account_id' => $account1100->id,
                'debit' => (float) $payment->amount,
                'credit' => 0,
                'notes' => 'Payment: '.$payment->id,
            ],
            [
                'account_id' => $account1200->id,
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'notes' => 'Payment for Invoice: '.$invoice?->invoice_number,
            ],
        ];

        $this->postJournalEntry->handle(
            clinicId: $clinicId,
            userId: $userId,
            lines: $journalLines,
            description: 'Payment received: '.$payment->id,
            referenceType: Payment::class,
            referenceId: $payment->id,
            entryDate: $payment->paid_at?->toDateString(),
        );
    }

    public function handleSalaryPaid(Salary $salary): void
    {
        $clinicId = $salary->clinic_id;
        $userId = $salary->paid_by ?? 1;

        $account1100 = $this->getCashAccount($clinicId, '1100');
        $account5100 = $this->getExpenseAccount($clinicId, '5100');

        if ($account1100 === null || $account5100 === null) {
            return;
        }

        $journalLines = [
            [
                'account_id' => $account5100->id,
                'debit' => (float) $salary->net_salary,
                'credit' => 0,
                'notes' => 'Salary for: '.$salary->period_month,
            ],
            [
                'account_id' => $account1100->id,
                'debit' => 0,
                'credit' => (float) $salary->net_salary,
                'notes' => 'Salary payment',
            ],
        ];

        $this->postJournalEntry->handle(
            clinicId: $clinicId,
            userId: $userId,
            lines: $journalLines,
            description: 'Salary paid: '.$salary->period_month,
            referenceType: Salary::class,
            referenceId: $salary->id,
            entryDate: $salary->paid_at?->toDateString(),
        );
    }

    private function getCashAccount(int $clinicId, string $code): ?Account
    {
        return Account::query()
            ->forClinic($clinicId)
            ->where('code', $code)
            ->first();
    }

    private function getReceivableAccount(int $clinicId, string $code): ?Account
    {
        return $this->getCashAccount($clinicId, $code);
    }

    private function getRevenueAccount(int $clinicId, string $code): ?Account
    {
        return $this->getCashAccount($clinicId, $code);
    }

    private function getExpenseAccount(int $clinicId, string $code): ?Account
    {
        return $this->getCashAccount($clinicId, $code);
    }
}
