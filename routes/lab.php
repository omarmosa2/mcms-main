<?php

use App\Http\Controllers\Lab\LabOrderController;
use App\Http\Controllers\Lab\LabResultController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('lab')->name('lab.')->group(function () {
    Route::get('/orders', [LabOrderController::class, 'index'])->middleware('permission:visit.view,medical.notes.create')->name('orders.index');
    Route::post('/orders', [LabOrderController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('orders.store');
    Route::get('/results', [LabResultController::class, 'index'])->middleware('permission:visit.view,medical.notes.create')->name('results.index');
    Route::post('/orders/{labOrderId}/results', [LabResultController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('results.store');
});
