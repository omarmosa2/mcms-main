<?php

use App\Http\Controllers\Admin\AdminSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('settings/admin')->name('admin-settings.')->group(function () {
        Route::get('clinic', [AdminSettingsController::class, 'clinic'])
            ->middleware('permission:settings.view')
            ->name('clinic');
        Route::put('clinic', [AdminSettingsController::class, 'updateClinic'])
            ->middleware('permission:settings.manage')
            ->name('clinic.update');

        Route::get('appointments', [AdminSettingsController::class, 'appointments'])
            ->middleware('permission:settings.view')
            ->name('appointments');
        Route::put('appointments', [AdminSettingsController::class, 'updateAppointments'])
            ->middleware('permission:settings.manage')
            ->name('appointments.update');

        Route::get('financial', [AdminSettingsController::class, 'financial'])
            ->middleware('permission:settings.view')
            ->name('financial');
        Route::put('financial', [AdminSettingsController::class, 'updateFinancial'])
            ->middleware('permission:settings.manage')
            ->name('financial.update');

        Route::get('permissions', [AdminSettingsController::class, 'permissions'])
            ->middleware('permission:settings.view')
            ->name('permissions');
        Route::put('permissions', [AdminSettingsController::class, 'updatePermissions'])
            ->middleware('permission:settings.manage')
            ->name('permissions.update');

        Route::get('appearance', [AdminSettingsController::class, 'appearance'])
            ->middleware('permission:settings.view')
            ->name('appearance');
        Route::put('appearance', [AdminSettingsController::class, 'updateAppearance'])
            ->middleware('permission:settings.manage')
            ->name('appearance.update');

        Route::get('security', [AdminSettingsController::class, 'security'])
            ->middleware('permission:settings.view')
            ->name('security');

        Route::get('diagnostics', [AdminSettingsController::class, 'diagnostics'])
            ->middleware('permission:settings.view')
            ->name('diagnostics');

        Route::get('support', [AdminSettingsController::class, 'support'])
            ->middleware('permission:settings.view')
            ->name('support');
        Route::put('support', [AdminSettingsController::class, 'updateSupport'])
            ->middleware('permission:settings.manage')
            ->name('support.update');
    });
});
