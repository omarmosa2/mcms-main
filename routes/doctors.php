<?php

use App\Http\Controllers\DoctorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,super_admin,clinic_admin'])->group(function (): void {
    Route::get('doctors/export', [DoctorController::class, 'export'])->name('doctors.export');
    Route::resource('doctors', DoctorController::class);
});
