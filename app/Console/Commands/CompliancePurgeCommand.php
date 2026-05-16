<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ComplianceRun;
use App\Models\SensitiveAccessLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('compliance:purge {--clinic= : Optional clinic ID} {--dry-run : Calculate deletions without deleting records}')]
#[Description('Purge old audit and sensitive access logs according to clinic security policy retention settings')]
class CompliancePurgeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clinicIdOption = $this->option('clinic');
        $clinicId = is_numeric($clinicIdOption) ? (int) $clinicIdOption : null;
        $dryRun = (bool) $this->option('dry-run');

        $clinics = Clinic::query()
            ->with('securityPolicy')
            ->when($clinicId !== null, fn ($query) => $query->whereKey($clinicId))
            ->get();

        if ($clinics->isEmpty()) {
            $this->error('No clinics matched the requested scope.');

            return self::FAILURE;
        }

        $summaryRows = [];

        foreach ($clinics as $clinic) {
            $auditRetention = (int) ($clinic->securityPolicy?->audit_retention_days
                ?? config('security.policy_defaults.audit_retention_days', 365));
            $sensitiveRetention = (int) ($clinic->securityPolicy?->sensitive_access_retention_days
                ?? config('security.policy_defaults.sensitive_access_retention_days', 365));

            $auditCutoff = now()->subDays($auditRetention);
            $sensitiveCutoff = now()->subDays($sensitiveRetention);

            $auditQuery = AuditLog::query()
                ->forClinic((int) $clinic->id)
                ->where('occurred_at', '<', $auditCutoff);

            $sensitiveQuery = SensitiveAccessLog::query()
                ->forClinic((int) $clinic->id)
                ->where('accessed_at', '<', $sensitiveCutoff);

            $auditCount = (clone $auditQuery)->count();
            $sensitiveCount = (clone $sensitiveQuery)->count();

            if (! $dryRun) {
                $auditQuery->delete();
                $sensitiveQuery->delete();
            }

            ComplianceRun::query()->create([
                'clinic_id' => (int) $clinic->id,
                'ran_by' => null,
                'run_type' => 'compliance.purge',
                'status' => 'completed',
                'summary' => [
                    'dry_run' => $dryRun,
                    'audit_deleted' => $auditCount,
                    'sensitive_deleted' => $sensitiveCount,
                    'audit_cutoff' => $auditCutoff->toISOString(),
                    'sensitive_cutoff' => $sensitiveCutoff->toISOString(),
                ],
                'ran_at' => now(),
            ]);

            $summaryRows[] = [
                'clinic_id' => (int) $clinic->id,
                'audit_deleted' => $auditCount,
                'sensitive_deleted' => $sensitiveCount,
            ];
        }

        $this->table(['Clinic', 'Audit rows', 'Sensitive rows'], array_map(
            fn (array $row): array => [
                $row['clinic_id'],
                $row['audit_deleted'],
                $row['sensitive_deleted'],
            ],
            $summaryRows,
        ));

        return self::SUCCESS;
    }
}
