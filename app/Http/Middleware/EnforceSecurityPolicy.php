<?php

namespace App\Http\Middleware;

use App\Services\Cache\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityPolicy
{
    private const SESSION_STARTED_AT_KEY = 'security_policy.session_started_at';

    private const LAST_ACTIVITY_AT_KEY = 'security_policy.last_activity_at';

    public function __construct(private CacheService $cacheService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->clinic_id === null) {
            return $next($request);
        }

        if (! Schema::hasTable('security_policies')) {
            return $next($request);
        }

        $policy = $this->cacheService->getSecurityPolicy((int) $user->clinic_id);

        if ($policy === null) {
            return $next($request);
        }

        if ($this->isExemptRoute($request)) {
            $this->touchActivity($request);

            return $next($request);
        }

        if (
            (bool) $policy->force_two_factor
            && ! $user->hasEnabledTwoFactorAuthentication()
        ) {
            return $this->forceTwoFactorResponse($request);
        }

        $sessionStartedAt = (int) $request->session()->get(self::SESSION_STARTED_AT_KEY, time());
        $lastActivityAt = (int) $request->session()->get(self::LAST_ACTIVITY_AT_KEY, time());
        $now = time();

        $maxSessionLifetimeSeconds = max(0, (int) $policy->session_lifetime_minutes) * 60;
        $idleTimeoutSeconds = max(0, (int) $policy->idle_timeout_minutes) * 60;

        if ($maxSessionLifetimeSeconds > 0 && ($now - $sessionStartedAt) >= $maxSessionLifetimeSeconds) {
            return $this->expireSession($request, 'Session expired due to clinic security policy.');
        }

        if ($idleTimeoutSeconds > 0 && ($now - $lastActivityAt) >= $idleTimeoutSeconds) {
            return $this->expireSession($request, 'Session expired due to inactivity policy.');
        }

        $this->touchActivity($request, $sessionStartedAt, $now);

        return $next($request);
    }

    private function forceTwoFactorResponse(Request $request): Response
    {
        $message = 'Two-factor authentication is required by clinic security policy.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        return to_route('security.edit')->with('status', $message);
    }

    private function expireSession(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return to_route('login')->with('status', $message);
    }

    private function touchActivity(Request $request, ?int $sessionStartedAt = null, ?int $lastActivityAt = null): void
    {
        $now = $lastActivityAt ?? time();

        $request->session()->put(self::SESSION_STARTED_AT_KEY, $sessionStartedAt ?? $now);
        $request->session()->put(self::LAST_ACTIVITY_AT_KEY, $now);
    }

    private function isExemptRoute(Request $request): bool
    {
        if ($request->routeIs('security.edit', 'security-policies.*', 'security-invitations.store', 'logout')) {
            return true;
        }

        return $request->is(
            'settings/security*',
            'logout',
            'user/two-factor-authentication',
            'user/two-factor-authentication/*',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
            'user/confirm-password',
            'user/confirmed-password-status',
        );
    }
}
