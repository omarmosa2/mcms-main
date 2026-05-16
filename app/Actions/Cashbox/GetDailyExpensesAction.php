<?php

namespace App\Actions\Cashbox;

use App\Actions\BaseAction;
use App\Models\Expense;

class GetDailyExpensesAction extends BaseAction
{
    public function handle(int $clinicId, string $date): float
    {
        return (float) Expense::query()
            ->forClinic($clinicId)
            ->where('expense_date', $date)
            ->where('status', 'approved')
            ->sum('amount');
    }
}
