<?php

use App\Http\Controllers\Pharmacy\DrugController;
use App\Http\Controllers\Pharmacy\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/drugs', [DrugController::class, 'index'])->middleware('permission:billing.view,payment.record')->name('drugs.index');
    Route::post('/drugs', [DrugController::class, 'store'])->middleware('permission:billing.generate,payment.record')->name('drugs.store');
    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->middleware('permission:visit.view,medical.notes.create')->name('prescriptions.index');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->middleware('permission:visit.update,medical.notes.create')->name('prescriptions.store');
    Route::post('/prescriptions/{prescriptionId}/dispense', [PrescriptionController::class, 'dispense'])->middleware('permission:billing.generate,payment.record')->name('prescriptions.dispense');
});
