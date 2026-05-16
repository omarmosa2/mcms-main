<?php

use App\Http\Controllers\Diagnostics\DiagnosticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('diagnostics')->name('diagnostics.')->group(function (): void {
        Route::get('lab-templates', [DiagnosticsController::class, 'labTemplates'])->name('lab-templates.index');
        Route::post('lab-templates', [DiagnosticsController::class, 'storeLabTemplate'])->name('lab-templates.store');
        Route::get('radiology-study-types', [DiagnosticsController::class, 'radiologyStudyTypes'])->name('radiology-study-types.index');
        Route::post('radiology-study-types', [DiagnosticsController::class, 'storeRadiologyStudyType'])->name('radiology-study-types.store');
    });
});
