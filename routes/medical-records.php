<?php

use App\Http\Controllers\MedicalRecords\FollowUpController;
use App\Http\Controllers\MedicalRecords\MedicalRecordController;
use App\Http\Controllers\MedicalRecords\MedicalRecordExportController;
use App\Http\Controllers\MedicalRecords\TreatmentPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('medical-records')->name('medical-records.')->group(function () {
    Route::get('/', [MedicalRecordController::class, 'index'])->middleware('permission:medical_record.view')->name('index');
    Route::get('/create', [MedicalRecordController::class, 'create'])->middleware('permission:medical_record.create')->name('create');
    Route::post('/', [MedicalRecordController::class, 'store'])->middleware('permission:medical_record.create')->name('store');
    Route::get('/{recordId}', [MedicalRecordController::class, 'show'])->middleware('permission:medical_record.view')->name('show');
    Route::match(['put', 'patch'], '/{recordId}', [MedicalRecordController::class, 'update'])->middleware('permission:medical_record.update')->name('update');
    Route::delete('/{recordId}', [MedicalRecordController::class, 'destroy'])->middleware('permission:medical_record.delete')->name('destroy');
    Route::get('/{recordId}/export', [MedicalRecordExportController::class, 'export'])->middleware('permission:medical_record.view')->name('export');

    Route::post('/treatment-plans', [TreatmentPlanController::class, 'store'])->middleware('permission:medical_record.update')->name('treatment-plans.store');
    Route::match(['put', 'patch'], '/treatment-plans/{planId}', [TreatmentPlanController::class, 'update'])->middleware('permission:medical_record.update')->name('treatment-plans.update');
    Route::delete('/treatment-plans/{planId}', [TreatmentPlanController::class, 'destroy'])->middleware('permission:medical_record.delete')->name('treatment-plans.destroy');

    Route::post('/follow-ups', [FollowUpController::class, 'store'])->middleware('permission:medical_record.update')->name('follow-ups.store');
    Route::match(['put', 'patch'], '/follow-ups/{followUpId}', [FollowUpController::class, 'update'])->middleware('permission:medical_record.update')->name('follow-ups.update');
    Route::delete('/follow-ups/{followUpId}', [FollowUpController::class, 'destroy'])->middleware('permission:medical_record.delete')->name('follow-ups.destroy');
});
