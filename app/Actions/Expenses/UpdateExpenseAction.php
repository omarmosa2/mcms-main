<?php

namespace App\Actions\Expenses;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateExpenseAction extends BaseAction
{
    public function __construct(private LogAuditAction $logAuditAction) {}

    public function handle(
        int $userId,
        int $expenseId,
        array $payload,
        ?UploadedFile $attachment = null,
    ): Expense {
        $expense = Expense::query()
            ->where('id', $expenseId)
            ->firstOrFail();

        $payload['updated_by'] = $userId;

        if ($attachment !== null) {
            if ($expense->attachment_path) {
                Storage::disk('public')->delete($expense->attachment_path);
            }
            $payload['attachment_path'] = $attachment->store('expenses/attachments');
        }

        $expense->update($payload);

        if ($expense->clinic_id !== null && $expense->clinic_id > 0) {
            $this->logAuditAction->handle(
                clinicId: (int) $expense->clinic_id,
                userId: $userId,
                action: 'expenses.update',
                metadata: [
                    'expense_id' => $expense->id,
                    'changes' => array_keys($payload),
                ],
            );
        }

        return $expense;
    }
}
