<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;

class UpdateAccountAction extends BaseAction
{
    public function handle(
        int $accountId,
        array $payload,
    ): Account {
        $account = Account::query()->findOrFail($accountId);

        if (isset($payload['code'])) {
            $exists = Account::query()
                ->forClinic($account->clinic_id)
                ->where('code', $payload['code'])
                ->where('id', '!=', $accountId)
                ->exists();

            if ($exists) {
                throw new \InvalidArgumentException(
                    "Account with code {$payload['code']} already exists.",
                );
            }
        }

        $account->update($payload);

        return $account->fresh();
    }
}
