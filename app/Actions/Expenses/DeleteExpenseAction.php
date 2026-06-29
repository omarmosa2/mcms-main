<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;
use Illuminate\Support\Facades\Storage;

class DeleteExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $userId,
        int $expenseId,
    ): void {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->firstOrFail();

        $amount = $expense->amount;
        $title = $expense->title;

        if ($expense->attachment_path) {
            Storage::disk('public')->delete($expense->attachment_path);
        }

        $expense->delete();

        if ($expense->clinic_id !== null && $expense->clinic_id > 0) {
            $this->logAuditAction->handle(
                clinicId: (int) $expense->clinic_id,
                userId: $userId,
                action: 'expenses.delete',
                metadata: [
                    'expense_id' => $expenseId,
                    'title' => $title,
                    'amount' => $amount,
                ],
            );
        }
    }
}
