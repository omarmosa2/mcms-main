<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;

class ApproveExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $userId,
        int $expenseId,
        string $status,
    ): Expense {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->firstOrFail();

        $expense->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        if ($expense->clinic_id !== null && $expense->clinic_id > 0) {
            $this->logAuditAction->handle(
                clinicId: (int) $expense->clinic_id,
                userId: $userId,
                action: 'expenses.update_status',
                metadata: [
                    'expense_id' => $expense->id,
                    'status' => $status,
                    'amount' => $expense->amount,
                ],
            );
        }

        return $expense;
    }
}
