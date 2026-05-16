<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\ComplianceRun;
use App\Models\SecurityPolicy;
use App\Models\SensitiveAccessLog;
use App\Models\UserInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('compliance:report {--clinic= : Optional clinic ID}')]
#[Description('Generate a compliance posture report for audit and security controls')]
class ComplianceReportCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clinicIdOption = $this->option('clinic');
        $clinicId = is_numeric($clinicIdOption) ? (int) $clinicIdOption : null;

        $clinicsQuery = Clinic::query();

        if ($clinicId !== null) {
            $clinicsQuery->whereKey($clinicId);
        }

        $clinicIds = $clinicsQuery->pluck('id')->all();

        if ($clinicIds === []) {
            $this->error('No clinics matched the requested scope.');

            return self::FAILURE;
        }

        $report = [
            'scope' => [
                'clinic_id' => $clinicId,
                'generated_at' => now()->toISOString(),
            ],
            'totals' => [
                'clinics' => count($clinicIds),
                'security_policies' => SecurityPolicy::query()->whereIn('clinic_id', $clinicIds)->count(),
                'audit_logs' => AuditLog::query()->whereIn('clinic_id', $clinicIds)->count(),
                'sensitive_access_logs' => SensitiveAccessLog::query()->whereIn('clinic_id', $clinicIds)->count(),
                'active_invitations' => UserInvitation::query()->whereIn('clinic_id', $clinicIds)->active()->count(),
            ],
            'recent_activity' => [
                'audit_events_last_24h' => AuditLog::query()
                    ->whereIn('clinic_id', $clinicIds)
                    ->where('occurred_at', '>=', now()->subDay())
                    ->count(),
                'sensitive_access_last_24h' => SensitiveAccessLog::query()
                    ->whereIn('clinic_id', $clinicIds)
                    ->where('accessed_at', '>=', now()->subDay())
                    ->count(),
            ],
        ];

        ComplianceRun::query()->create([
            'clinic_id' => $clinicId,
            'ran_by' => null,
            'run_type' => 'compliance.report',
            'status' => 'completed',
            'summary' => $report,
            'ran_at' => now(),
        ]);

        $this->line((string) json_encode($report, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
