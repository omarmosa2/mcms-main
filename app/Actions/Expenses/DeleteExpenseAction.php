<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;

class DeleteExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $expenseId,
    ): void {
        $expense = Expense::query()
            ->forClinic($clinicId)
            ->where('id', $expenseId)
            ->firstOrFail();

        $amount = $expense->amount;

        $expense->delete();

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'expenses.delete',
            metadata: [
                'expense_id' => $expenseId,
                'amount' => $amount,
            ],
        );
    }
}
