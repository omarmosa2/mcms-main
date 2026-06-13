<?php

use App\Http\Controllers\DoctorLeaves\DoctorLeaveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('doctor-leaves')
    ->name('doctor-leaves.')
    ->group(function (): void {
        Route::get('/', [DoctorLeaveController::class, 'index'])->middleware('permission:doctor_schedule.view')->name('index');
        Route::post('/', [DoctorLeaveController::class, 'store'])->middleware('permission:doctor_schedule.create')->name('store');
        Route::match(['put', 'patch'], '/{doctorLeaveId}', [DoctorLeaveController::class, 'update'])->middleware('permission:doctor_schedule.update')->name('update');
        Route::patch('/{doctorLeaveId}/cancel', [DoctorLeaveController::class, 'cancel'])->middleware('permission:doctor_schedule.update')->name('cancel');
        Route::delete('/{doctorLeaveId}', [DoctorLeaveController::class, 'destroy'])->middleware('permission:doctor_schedule.update')->name('destroy');
    });
