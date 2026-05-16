<?php

namespace App\Actions\Cashbox;

use App\Models\Cashbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCashboxAction
{
    public function handle(int $clinicId, int $userId, int $cashboxId, array $payload): Cashbox
    {
        $cashbox = Cashbox::query()
            ->forClinic($clinicId)
            ->findOrFail($cashboxId);

        if ($cashbox->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => 'Cannot update a closed cashbox.',
            ]);
        }

        $validated = DB::transaction(function () use ($cashbox, $payload) {
            $updates = [];

            if (isset($payload['opening_balance'])) {
                $updates['opening_balance'] = $payload['opening_balance'];
            }

            if (isset($payload['notes'])) {
                $updates['notes'] = $payload['notes'];
            }

            if (empty($updates)) {
                return $cashbox;
            }

            $cashbox->update($updates);

            return $cashbox->fresh();
        });

        return $validated;
    }
}
