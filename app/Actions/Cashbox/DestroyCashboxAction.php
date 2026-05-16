<?php

namespace App\Actions\Cashbox;

use App\Models\Cashbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DestroyCashboxAction
{
    public function handle(int $clinicId, int $userId, int $cashboxId): void
    {
        DB::transaction(function () use ($clinicId, $cashboxId) {
            $cashbox = Cashbox::query()
                ->forClinic($clinicId)
                ->findOrFail($cashboxId);

            if ($cashbox->status === 'open') {
                throw ValidationException::withMessages([
                    'status' => 'Cannot delete an open cashbox. Close it first.',
                ]);
            }

            $cashbox->delete();
        });
    }

    public function bulkDestroy(int $clinicId, int $userId, array $ids): int
    {
        return DB::transaction(function () use ($clinicId, $ids) {
            $count = Cashbox::query()
                ->forClinic($clinicId)
                ->whereIn('id', $ids)
                ->where('status', 'closed')
                ->delete();

            return $count;
        });
    }
}
