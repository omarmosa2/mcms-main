<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;

class CreateAccountAction extends BaseAction
{
    public function handle(
        int $clinicId,
        array $payload,
    ): Account {
        $exists = Account::query()
            ->forClinic($clinicId)
            ->where('code', $payload['code'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException(
                "Account with code {$payload['code']} already exists.",
            );
        }

        return Account::query()->create([
            'clinic_id' => $clinicId,
            'parent_id' => $payload['parent_id'] ?? null,
            'code' => $payload['code'],
            'name' => $payload['name'],
            'type' => $payload['type'],
            'opening_balance' => $payload['opening_balance'] ?? 0,
            'is_active' => $payload['is_active'] ?? true,
        ]);
    }
}
