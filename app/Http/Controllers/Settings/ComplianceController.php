<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplianceRun;
use App\Models\SecurityPolicy;
use App\Models\SensitiveAccessLog;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ComplianceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isClinicSecurityManager()) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to view compliance controls.');
        }

        $clinicId = $this->resolveClinicId($request);
        $today = now()->toDateString();

        $securityPolicy = SecurityPolicy::query()->forClinic($clinicId)->first();

        $recentAuditEvents = AuditLog::query()
            ->forClinic($clinicId)
            ->with('user')
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $recentSensitiveAccess = SensitiveAccessLog::query()
            ->forClinic($clinicId)
            ->with('user')
            ->latest('accessed_at')
            ->limit(10)
            ->get();

        $recentRuns = ComplianceRun::query()
            ->forClinic($clinicId)
            ->latest('ran_at')
            ->limit(10)
            ->get();

        return Inertia::render('settings/Compliance', [
            'kpis' => [
                'audit_events_today' => AuditLog::query()
                    ->forClinic($clinicId)
                    ->whereDate('occurred_at', $today)
                    ->count(),
                'sensitive_access_today' => SensitiveAccessLog::query()
                    ->forClinic($clinicId)
                    ->whereDate('accessed_at', $today)
                    ->count(),
                'pending_invitations' => UserInvitation::query()
                    ->forClinic($clinicId)
                    ->active()
                    ->count(),
                'policy_configured' => $securityPolicy !== null,
            ],
            'security_policy' => $securityPolicy,
            'recent_audit_events' => $recentAuditEvents->map(fn (AuditLog $auditLog): array => [
                'id' => $auditLog->id,
                'action' => $auditLog->action,
                'user' => $auditLog->user?->name,
                'occurred_at' => $auditLog->occurred_at?->toISOString(),
                'metadata' => $auditLog->metadata,
            ])->values(),
            'recent_sensitive_access' => $recentSensitiveAccess->map(fn (SensitiveAccessLog $sensitiveAccessLog): array => [
                'id' => $sensitiveAccessLog->id,
                'resource_type' => $sensitiveAccessLog->resource_type,
                'resource_id' => $sensitiveAccessLog->resource_id,
                'user' => $sensitiveAccessLog->user?->name,
                'reason' => $sensitiveAccessLog->reason,
                'accessed_at' => $sensitiveAccessLog->accessed_at?->toISOString(),
            ])->values(),
            'recent_runs' => $recentRuns->map(fn (ComplianceRun $complianceRun): array => [
                'id' => $complianceRun->id,
                'run_type' => $complianceRun->run_type,
                'status' => $complianceRun->status,
                'summary' => $complianceRun->summary,
                'ran_at' => $complianceRun->ran_at?->toISOString(),
            ])->values(),
        ]);
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }
}
