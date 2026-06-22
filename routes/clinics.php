<?php

use App\Http\Controllers\Clinics\ClinicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin,super_admin,clinic_admin'])->prefix('clinics')->name('clinics.')->group(function () {
    Route::get('/', [ClinicController::class, 'index'])->middleware('permission:department.view')->name('index');
    Route::post('/', [ClinicController::class, 'store'])->middleware('permission:department.create')->name('store');
    Route::delete('/bulk', [ClinicController::class, 'bulkDestroy'])->middleware('permission:department.delete')->name('bulk-destroy');
    Route::get('/{clinicId}', [ClinicController::class, 'show'])->middleware('permission:department.view')->name('show');
    Route::match(['put', 'patch'], '/{clinicId}', [ClinicController::class, 'update'])->middleware('permission:department.update')->name('update');
    Route::delete('/{clinicId}', [ClinicController::class, 'destroy'])->middleware('permission:department.delete')->name('destroy');
});
