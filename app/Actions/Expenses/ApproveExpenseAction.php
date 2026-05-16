<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;

class ApproveExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $expenseId,
        bool $approve = true,
    ): Expense {
        $expense = Expense::query()
            ->forClinic($clinicId)
            ->where('id', $expenseId)
            ->firstOrFail();
        $expense->update([
            'approved_by' => $userId,
            'approved_at' => now(),
            'status' => $approve ? Expense::STATUS_APPROVED : Expense::STATUS_REJECTED,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'expenses.approve',
            metadata: [
                'expense_id' => $expense->id,
                'approved' => $approve,
                'amount' => $expense->amount,
            ],
        );

        return $expense;
    }
}
