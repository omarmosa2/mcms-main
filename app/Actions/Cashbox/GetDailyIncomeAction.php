<?php

namespace App\Actions\Cashbox;

use App\Actions\BaseAction;
use App\Models\Payment;

class GetDailyIncomeAction extends BaseAction
{
    public function handle(int $clinicId, string $date): float
    {
        return (float) Payment::query()
            ->forClinic($clinicId)
            ->whereDate('paid_at', $date)
            ->where('status', 'recorded')
            ->sum('amount');
    }
}
