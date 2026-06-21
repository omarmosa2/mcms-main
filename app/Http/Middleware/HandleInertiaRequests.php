<?php

namespace App\Http\Middleware;

use App\Models\ClinicSetting;
use App\Models\User;
use App\Services\Cache\CacheService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private CacheService $cacheService,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $permissions = [];
        $roles = [];
        $branding = null;
        $securityPolicy = null;
        $canManageSecurityPolicies = false;
        $doctorClinic = null;

        if ($request->user() !== null) {
            $user = $request->user();
            $clinicId = $user->clinic_id;

            $roles = $user->roleNamesForCurrentClinic()->all();

            if ($clinicId !== null) {
                $permissions = $this->cacheService
                    ->getUserPermissions($user->id, (int) $clinicId)
                    ->all();
            }

            if (in_array('super_admin', $roles, true)) {
                $permissions = ['*'];
            }

            $canManageSecurityPolicies = $user->isClinicSecurityManager();

            if ($clinicId !== null) {
                $clinicIdInt = (int) $clinicId;

                $branding = $this->cacheService->getBrandingSettings($clinicIdInt);

                $securityPolicy = $this->cacheService->getSecurityPolicy($clinicIdInt);

                $clinicName = ClinicSetting::get($clinicIdInt, 'clinic', 'name');
            }

            if (in_array('doctor', $roles, true) && $user->doctorProfile !== null) {
                $doctorProfile = $user->doctorProfile;
                $clinic = $user->clinic;

                $doctorClinic = [
                    'name' => $clinic?->name,
                    'specialty' => $doctorProfile->specialty,
                ];
            }
        }

        $locale = app()->getLocale();
        $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'clinic_name' => $clinicName ?? null,
            'auth' => [
                'user' => $request->user() !== null ? $this->sharedUser($request->user()) : null,
                'roles' => $roles,
                'permissions' => $permissions,
                'doctor_clinic' => $doctorClinic,
            ],
            'branding' => [
                'company_name' => $branding?->company_name,
                'logo_path' => $branding?->logo_path,
                'theme_tokens' => $branding?->theme_tokens,
                'locale_default' => $branding?->locale_default ?? $locale,
                'domain' => $branding?->domain,
            ],
            'localization' => [
                'locale' => $locale,
                'direction' => $direction,
            ],
            'security' => [
                'can_manage_policies' => $canManageSecurityPolicies,
                'policy' => $securityPolicy !== null ? [
                    'password_min_length' => $securityPolicy->password_min_length,
                    'require_mixed_case' => $securityPolicy->require_mixed_case,
                    'require_numbers' => $securityPolicy->require_numbers,
                    'require_symbols' => $securityPolicy->require_symbols,
                    'session_lifetime_minutes' => $securityPolicy->session_lifetime_minutes,
                    'idle_timeout_minutes' => $securityPolicy->idle_timeout_minutes,
                    'force_two_factor' => $securityPolicy->force_two_factor,
                    'confirm_password_for_security_actions' => $securityPolicy->confirm_password_for_security_actions,
                    'audit_retention_days' => $securityPolicy->audit_retention_days,
                    'sensitive_access_retention_days' => $securityPolicy->sensitive_access_retention_days,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(User $user): array
    {
        return [
            'id' => $user->id,
            'clinic_id' => $user->clinic_id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? null,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
