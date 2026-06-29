<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;
use Illuminate\Http\UploadedFile;

class CreateExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $userId,
        array $payload,
        ?UploadedFile $attachment = null,
    ): Expense {
        $payload['created_by'] = $userId;
        $payload['user_id'] = $userId;

        if (empty($payload['description'])) {
            $payload['description'] = $payload['title'] ?? '';
        }

        if ($attachment !== null) {
            $payload['attachment_path'] = $attachment->store('expenses/attachments');
        }

        $expense = Expense::create($payload);

        if ($expense->clinic_id !== null && $expense->clinic_id > 0) {
            $this->logAuditAction->handle(
                clinicId: (int) $expense->clinic_id,
                userId: $userId,
                action: 'expenses.create',
                metadata: [
                    'expense_id' => $expense->id,
                    'expense_number' => $expense->expense_number,
                    'amount' => $expense->amount,
                    'title' => $expense->title,
                    'category_id' => $expense->category_id,
                ],
            );
        }

        return $expense;
    }
}
