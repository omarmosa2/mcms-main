<?php

use App\Http\Controllers\Doctor\DoctorPrescriptionController;
use App\Http\Controllers\Doctor\DoctorWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/workspace', [DoctorWorkspaceController::class, 'workspace'])->name('workspace');
    Route::get('/appointments/today', [DoctorWorkspaceController::class, 'todayAppointments'])->name('today-appointments');
    Route::get('/follow-ups', [DoctorWorkspaceController::class, 'followUps'])->name('follow-ups');
    Route::get('/profile', [DoctorWorkspaceController::class, 'profile'])->name('profile');

    Route::get('/prescriptions', [DoctorPrescriptionController::class, 'index'])->name('prescriptions');
    Route::get('/prescriptions/create', [DoctorPrescriptionController::class, 'create'])->name('prescriptions.create');
    Route::post('/prescriptions', [DoctorPrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::get('/prescriptions/{prescriptionId}', [DoctorPrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::get('/prescriptions/{prescriptionId}/edit', [DoctorPrescriptionController::class, 'edit'])->name('prescriptions.edit');
    Route::match(['put', 'patch'], '/prescriptions/{prescriptionId}', [DoctorPrescriptionController::class, 'update'])->name('prescriptions.update');
    Route::get('/prescriptions/{prescriptionId}/print', [DoctorPrescriptionController::class, 'print'])->name('prescriptions.print');
    Route::get('/prescriptions/{prescriptionId}/pdf', [DoctorPrescriptionController::class, 'pdf'])->name('prescriptions.pdf');
});
