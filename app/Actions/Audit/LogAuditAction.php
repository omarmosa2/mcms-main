<?php

namespace App\Actions\Audit;

use App\Actions\BaseAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class LogAuditAction extends BaseAction
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        int $clinicId,
        ?int $userId,
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
