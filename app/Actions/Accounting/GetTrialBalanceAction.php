<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

class GetTrialBalanceAction extends BaseAction
{
    public function handle(
        int $clinicId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $accounts = Account::query()
            ->forClinic($clinicId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $query = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($clinicId, $dateFrom, $dateTo) {
                $q->forClinic($clinicId)
                    ->where('status', 'posted');

                if ($dateFrom) {
                    $q->where('entry_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->where('entry_date', '<=', $dateTo);
                }
            });

        $balances = new Collection;

        foreach ($accounts as $account) {
            $accountDebits = $query->clone()
                ->where('account_id', $account->id)
                ->sum('debit');

            $accountCredits = $query->clone()
                ->where('account_id', $account->id)
                ->sum('credit');

            $balance = match ($account->type) {
                Account::TYPE_ASSET, Account::TYPE_EXPENSE => $account->opening_balance + $accountDebits - $accountCredits,
                Account::TYPE_LIABILITY, Account::TYPE_EQUITY, Account::TYPE_REVENUE => $account->opening_balance + $accountCredits - $accountDebits,
                default => 0,
            };

            if (abs($balance) > 0.01 || $accountDebits > 0 || $accountCredits > 0) {
                $balances->push([
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'opening_balance' => $account->opening_balance,
                    'debit' => $accountDebits,
                    'credit' => $accountCredits,
                    'balance' => round($balance, 2),
                ]);
            }
        }

        $totalDebits = $balances->sum('debit');
        $totalCredits = $balances->sum('credit');

        return [
            'accounts' => $balances->values(),
            'summary' => [
                'total_debit' => round($totalDebits, 2),
                'total_credit' => round($totalCredits, 2),
                'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
            ],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
}
