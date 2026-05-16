<?php

use App\Http\Controllers\Visits\VisitController;
use App\Http\Controllers\Visits\VisitDiagnosisController;
use App\Http\Controllers\Visits\VisitExportController;
use App\Http\Controllers\Visits\VisitVitalSignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('visits')->name('visits.')->group(function () {
    Route::get('/', [VisitController::class, 'index'])->middleware('permission:visit.start,visit.update,visit.complete')->name('index');
    Route::get('/export', [VisitExportController::class, 'export'])->middleware('permission:visit.start,visit.update,visit.complete')->name('export');
    Route::get('/export/pdf', [VisitExportController::class, 'exportPdf'])->middleware('permission:visit.start,visit.update,visit.complete')->name('export.pdf');
    Route::post('/', [VisitController::class, 'store'])->middleware('permission:visit.start')->name('store');
    Route::delete('/bulk', [VisitController::class, 'bulkDestroy'])->middleware('permission:visits.*')->name('bulk-destroy');
    Route::get('/{visitId}', [VisitController::class, 'show'])->middleware('permission:visit.start,visit.update,visit.complete')->name('show');
    Route::match(['put', 'patch'], '/{visitId}', [VisitController::class, 'update'])->middleware('permission:visit.update,medical.notes.create')->name('update');
    Route::patch('/{visitId}/status', [VisitController::class, 'transitionStatus'])->middleware('permission:visit.update,visit.complete')->name('transition-status');
    Route::post('/{visitId}/diagnoses', [VisitDiagnosisController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('diagnoses.store');
    Route::post('/{visitId}/vitals', [VisitVitalSignController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('vitals.store');
    Route::delete('/{visitId}', [VisitController::class, 'destroy'])->middleware('permission:visits.*')->name('destroy');
});
