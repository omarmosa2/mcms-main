<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\UpsertSecurityPolicyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller implements HasMiddleware
{
    public function __construct(private UpsertSecurityPolicyAction $upsertSecurityPolicyAction) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                ? [new Middleware('password.confirm', only: ['edit'])]
                : [];
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $user = $request->user();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManageSecurityPolicies' => $user?->isClinicSecurityManager() ?? false,
            'policy' => null,
            'invitation_roles' => (array) config('security.invitable_roles'),
            'pending_invitations' => [],
            'latest_invitation_url' => $request->session()->get('latest_invitation_url'),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        if ($user !== null && $user->clinic_id !== null && $user->isClinicSecurityManager()) {
            $policy = $this->upsertSecurityPolicyAction->handle((int) $user->clinic_id, null);

            $props['policy'] = [
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

            $props['pending_invitations'] = UserInvitation::query()
                ->forClinic((int) $user->clinic_id)
                ->active()
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (UserInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'full_name' => $invitation->full_name,
                    'role_name' => $invitation->role_name,
                    'expires_at' => $invitation->expires_at?->toDateTimeString(),
                    'invitation_url' => route('register', ['invitation' => $invitation->token]),
                ])
                ->values();
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
