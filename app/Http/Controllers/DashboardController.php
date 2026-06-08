<?php

namespace App\Http\Controllers;

use App\Services\Cache\CacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $clinicId = (int) $user?->clinic_id;

        if ($clinicId === 0) {
            abort(403, 'Clinic context is required.');
        }

        if ($user !== null && $user->hasRole('doctor')) {
            return to_route('doctor.workspace');
        }

        return Inertia::render('Dashboard', [
            'chartStats' => Inertia::defer(fn () => $this->cacheService->getDashboardStats($clinicId)),
        ]);
    }
}
