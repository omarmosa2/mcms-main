<?php

namespace App\Actions\Cashbox;

use App\Actions\Audit\LogAuditAction;
use App\Actions\BaseAction;
use App\Models\Cashbox;
use Illuminate\Validation\ValidationException;

class CloseCashboxAction extends BaseAction
{
    public function __construct(
        private LogAuditAction $logAuditAction,
        private GetDailyIncomeAction $getDailyIncomeAction,
        private GetDailyExpensesAction $getDailyExpensesAction,
    ) {}

    public function handle(
        int $clinicId,
        int $userId,
        int $cashboxId,
        array $payload = [],
    ): Cashbox {
        $cashbox = Cashbox::query()
            ->forClinic($clinicId)
            ->where('id', $cashboxId)
            ->firstOrFail();

        if ($cashbox->status === Cashbox::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'This cashbox is already closed.',
            ]);
        }

        $dailyIncome = $this->getDailyIncomeAction->handle($clinicId, $cashbox->box_date);
        $dailyExpenses = $this->getDailyExpensesAction->handle($clinicId, $cashbox->box_date);

        $closingBalance = $cashbox->opening_balance + $dailyIncome - $dailyExpenses;

        $cashbox->update([
            'total_income' => $dailyIncome,
            'total_expenses' => $dailyExpenses,
            'closing_balance' => $closingBalance,
            'status' => Cashbox::STATUS_CLOSED,
            'closed_by' => $userId,
            'closed_at' => now(),
            'notes' => $payload['notes'] ?? $cashbox->notes,
        ]);

        $this->logAuditAction->handle(
            clinicId: $clinicId,
            userId: $userId,
            action: 'cashbox.close',
            auditable: $cashbox,
            metadata: [
                'cashbox_id' => $cashbox->id,
                'opening_balance' => $cashbox->opening_balance,
                'total_income' => $dailyIncome,
                'total_expenses' => $dailyExpenses,
                'closing_balance' => $closingBalance,
            ],
        );

        return $cashbox;
    }
}
