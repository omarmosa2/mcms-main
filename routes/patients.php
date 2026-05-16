<?php

use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientImportExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('patients')->name('patients.')->group(function () {
    Route::get('/', [PatientController::class, 'index'])->middleware('permission:patient.view')->name('index');
    Route::post('/', [PatientController::class, 'store'])->middleware('permission:patient.create')->name('store');
    Route::get('/export', [PatientImportExportController::class, 'export'])->middleware('permission:patient.view')->name('export');
    Route::get('/import', [PatientImportExportController::class, 'importView'])->middleware('permission:patient.create')->name('import');
    Route::post('/import/preview', [PatientImportExportController::class, 'preview'])->middleware('permission:patient.create')->name('import.preview');
    Route::post('/import', [PatientImportExportController::class, 'import'])->middleware('permission:patient.create')->name('import.store');
    Route::get('/import/status', [PatientImportExportController::class, 'importStatus'])->middleware('permission:patient.view')->name('import.status');
    Route::post('/{patientId}/attachments', [PatientController::class, 'storeAttachment'])->middleware('permission:patient.update')->name('attachments.store');
    Route::get('/{patientId}/attachments/{attachmentId}/download', [PatientController::class, 'downloadAttachment'])->middleware('permission:patient.view')->name('attachments.download');
    Route::delete('/{patientId}/attachments/{attachmentId}', [PatientController::class, 'destroyAttachment'])->middleware('permission:patient.update')->name('attachments.destroy');
    Route::delete('/bulk', [PatientController::class, 'bulkDestroy'])->middleware('permission:patient.delete')->name('bulk-destroy');
    Route::get('/{patientId}', [PatientController::class, 'show'])->middleware('permission:patient.view')->name('show');
    Route::match(['put', 'patch'], '/{patientId}', [PatientController::class, 'update'])->middleware('permission:patient.update')->name('update');
    Route::delete('/{patientId}', [PatientController::class, 'destroy'])->middleware('permission:patient.delete')->name('destroy');
});
