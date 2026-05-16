<?php

use App\Http\Controllers\Radiology\RadiologyOrderController;
use App\Http\Controllers\Radiology\RadiologyReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('radiology')->name('radiology.')->group(function () {
    Route::get('/orders', [RadiologyOrderController::class, 'index'])->middleware('permission:visit.view,medical.notes.create')->name('orders.index');
    Route::post('/orders', [RadiologyOrderController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('orders.store');
    Route::get('/reports', [RadiologyReportController::class, 'index'])->middleware('permission:visit.view,medical.notes.create')->name('reports.index');
    Route::post('/orders/{radiologyOrderId}/reports', [RadiologyReportController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('reports.store');
});
