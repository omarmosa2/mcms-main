<?php

use App\Http\Controllers\Doctors\DoctorProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('doctors')->name('doctors.')->group(function () {
    Route::get('/', [DoctorProfileController::class, 'index'])->middleware('permission:doctor_profile.view')->name('index');
    Route::post('/', [DoctorProfileController::class, 'store'])->middleware('permission:doctor_profile.create')->name('store');
    Route::delete('/bulk', [DoctorProfileController::class, 'bulkDestroy'])->middleware('permission:doctor_profile.delete')->name('bulk-destroy');
    Route::get('/{doctorProfileId}', [DoctorProfileController::class, 'show'])->middleware('permission:doctor_profile.view')->name('show');
    Route::match(['put', 'patch'], '/{doctorProfileId}', [DoctorProfileController::class, 'update'])->middleware('permission:doctor_profile.update')->name('update');
    Route::delete('/{doctorProfileId}', [DoctorProfileController::class, 'destroy'])->middleware('permission:doctor_profile.delete')->name('destroy');
});
