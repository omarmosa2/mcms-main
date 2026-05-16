<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\UpsertSecurityPolicyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSecurityPolicyRequest;
use App\Models\SecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SecurityPolicyController extends Controller
{
    public function __construct(private UpsertSecurityPolicyAction $upsertSecurityPolicyAction) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->isClinicSecurityManager()) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to manage security policies.');
        }

        $clinicId = $this->resolveClinicId($request);

        $policy = $this->upsertSecurityPolicyAction->handle(
            clinicId: $clinicId,
            updatedBy: null,
        );

        return response()->json([
            'data' => $this->toPayload($policy),
        ]);
    }

    public function update(UpdateSecurityPolicyRequest $request): JsonResponse|RedirectResponse
    {
        $clinicId = $this->resolveClinicId($request);

        $policy = $this->upsertSecurityPolicyAction->handle(
            clinicId: $clinicId,
            updatedBy: (int) $request->user()->id,
            payload: $request->validated(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($policy),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Security policy updated successfully.']);

        return to_route('security.edit');
    }

    private function resolveClinicId(Request $request): int
    {
        $clinicId = $request->user()?->clinic_id;

        if ($clinicId === null) {
            abort(Response::HTTP_FORBIDDEN, 'Clinic context is required.');
        }

        return (int) $clinicId;
    }

    /**
     * @return array<string, bool|int>
     */
    private function toPayload(SecurityPolicy $policy): array
    {
        return [
            'password_min_length' => (int) $policy->password_min_length,
            'require_mixed_case' => (bool) $policy->require_mixed_case,
            'require_numbers' => (bool) $policy->require_numbers,
            'require_symbols' => (bool) $policy->require_symbols,
            'session_lifetime_minutes' => (int) $policy->session_lifetime_minutes,
            'idle_timeout_minutes' => (int) $policy->idle_timeout_minutes,
            'force_two_factor' => (bool) $policy->force_two_factor,
            'confirm_password_for_security_actions' => (bool) $policy->confirm_password_for_security_actions,
            'audit_retention_days' => (int) $policy->audit_retention_days,
            'sensitive_access_retention_days' => (int) $policy->sensitive_access_retention_days,
        ];
    }
}
