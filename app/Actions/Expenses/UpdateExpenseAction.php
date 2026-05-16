<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;

class UpdateExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $expenseId,
        array $payload,
    ): Expense {
        $expense = Expense::query()
            ->forClinic($clinicId)
            ->where('id', $expenseId)
            ->firstOrFail();
        $expense->update($payload);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'expenses.update',
            metadata: [
                'expense_id' => $expense->id,
                'changes' => array_keys($payload),
            ],
        );

        return $expense;
    }
}
