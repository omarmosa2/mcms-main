<?php

namespace App\Http\Middleware;

use App\Models\Clinic;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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

        $user = $request->user();

        if ($user !== null && $user->clinic_id !== null) {
            try {
                $clinic = Clinic::query()
                    ->with('brandingSetting')
                    ->find($user->clinic_id);
                $branding = $clinic?->brandingSetting;
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'branding_settings')) {
                    throw $e;
                }

                $clinic = Clinic::query()->find($user->clinic_id);
                $branding = null;
            }

            if ($clinic !== null) {
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
