<?php

use App\Http\Controllers\Doctor\DoctorWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'doctor'])->prefix('doctor')->name('doctor.')->group(function () {
    Route::get('/workspace', [DoctorWorkspaceController::class, 'workspace'])->name('workspace');
    Route::get('/appointments/today', [DoctorWorkspaceController::class, 'todayAppointments'])->name('today-appointments');
    Route::get('/prescriptions', [DoctorWorkspaceController::class, 'prescriptions'])->name('prescriptions');
    Route::get('/follow-ups', [DoctorWorkspaceController::class, 'followUps'])->name('follow-ups');
    Route::get('/profile', [DoctorWorkspaceController::class, 'profile'])->name('profile');
});
