<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyClinicBranding
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = config('app.locale');
        $direction = str_starts_with((string) $locale, 'ar') ? 'rtl' : 'ltr';
        $brandingTableExists = Schema::hasTable('branding_settings');

        $user = $request->user();

        if ($user !== null && $user->clinic_id !== null) {
            $clinicQuery = Clinic::query();

            if ($brandingTableExists) {
                $clinicQuery->with('brandingSetting');
            }

            $clinic = $clinicQuery->find($user->clinic_id);

            if ($clinic !== null) {
                $branding = $brandingTableExists ? $clinic->brandingSetting : null;

                $locale = (string) ($branding?->locale_default ?: config('app.locale'));
                $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';

                app()->setLocale($locale);

                if ($clinic->timezone !== null && $clinic->timezone !== '') {
                    date_default_timezone_set($clinic->timezone);
                }

                $applicationName = $branding?->company_name ?: $clinic->name;

                if (is_string($applicationName) && $applicationName !== '') {
                    config(['app.name' => $applicationName]);
                }
            }
        }

        View::share('appDirection', $direction);

        return $next($request);
    }
}
