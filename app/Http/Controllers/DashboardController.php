<?php

namespace App\Http\Controllers;

use App\Services\Cache\CacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $clinicId = (int) $request->user()?->clinic_id;

        if ($clinicId === 0) {
            abort(403, 'Clinic context is required.');
        }

        $stats = $this->cacheService->getDashboardStats($clinicId);

        return Inertia::render('Dashboard', [
            'chartStats' => Inertia::defer(fn () => $stats),
        ]);
    }
}
