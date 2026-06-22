<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorManagementAdministrator
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isClinicSecurityManager()) {
            abort(Response::HTTP_FORBIDDEN, 'Doctor management is restricted to administrators.');
        }

        return $next($request);
    }
}
