<?php

use App\Http\Controllers\Portal\PatientPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('patients')->name('patients.')->group(function () {
    Route::post('/{patientId}/portal-tokens', [PatientPortalController::class, 'issueToken'])->middleware('permission:patient.view')->name('portal-tokens.store');
});

Route::get('/portal/{plainToken}', [PatientPortalController::class, 'show'])->name('portal.show');
Route::post('/portal/{plainToken}/appointments/{appointment}', [PatientPortalController::class, 'updateAppointment'])->name('portal.appointments.update');
