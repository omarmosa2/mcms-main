<?php

use App\Http\Controllers\Monitoring\HealthController;
use App\Http\Controllers\Monitoring\MetricsController;
use Illuminate\Support\Facades\Route;

Route::prefix('monitoring')->name('monitoring.')->group(function (): void {
    Route::get('health', [HealthController::class, 'check'])->name('health');
    Route::get('metrics', [MetricsController::class, 'index'])->name('metrics');
});
