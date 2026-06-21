<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthenticated.');
        }

        if ($user->clinic_id === null) {
            return $next($request);
        }

        if ($permissions === []) {
            abort(Response::HTTP_FORBIDDEN, 'Permission is required.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have the required permission.');
    }
}
