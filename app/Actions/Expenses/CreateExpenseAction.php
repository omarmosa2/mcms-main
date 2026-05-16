<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;

class CreateExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $clinicId,
        int $userId,
        array $payload,
    ): Expense {
        $payload['clinic_id'] = $clinicId;
        $payload['user_id'] = $userId;

        $expense = Expense::create($payload);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'expenses.create',
            metadata: [
                'expense_id' => $expense->id,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'category_id' => $expense->category_id,
            ],
        );

        return $expense;
    }
}
