<?php

use App\Http\Middleware\ApplyClinicBranding;
use App\Http\Middleware\EnforceSecurityPolicy;
use App\Http\Middleware\EnsureDoctorRole;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'doctor' => EnsureDoctorRole::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            ApplyClinicBranding::class,
            HandleInertiaRequests::class,
            EnforceSecurityPolicy::class,
            SecurityHeaders::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response) {
            $status = $response->getStatusCode();

            if (in_array($status, [404, 403, 500, 503, 401, 429])) {
                return Inertia::render('Error', [
                    'status' => $status,
                    'message' => $response->getOriginalContent()['message'] ?? null,
                ])->toResponse(request())->setStatusCode($status);
            }

            return $response;
        });
    })->create();
