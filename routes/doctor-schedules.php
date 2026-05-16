<?php

use App\Http\Controllers\DoctorSchedules\DoctorScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('doctor-schedules')->name('doctor-schedules.')->group(function () {
    Route::get('/', [DoctorScheduleController::class, 'index'])->middleware('permission:doctor_schedule.view')->name('index');
    Route::post('/', [DoctorScheduleController::class, 'store'])->middleware('permission:doctor_schedule.create')->name('store');
    Route::match(['put', 'patch'], '/{doctorScheduleId}', [DoctorScheduleController::class, 'update'])->middleware('permission:doctor_schedule.update')->name('update');
    Route::delete('/{doctorScheduleId}', [DoctorScheduleController::class, 'destroy'])->middleware('permission:doctor_schedule.delete')->name('destroy');
});
