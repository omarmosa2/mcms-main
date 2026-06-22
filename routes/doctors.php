<?php

use App\Http\Controllers\DoctorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,super_admin,clinic_admin'])->group(function (): void {
    Route::resource('doctors', DoctorController::class);
});
