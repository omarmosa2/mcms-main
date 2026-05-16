<?php

use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\AppointmentExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('appointments')->name('appointments.')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->middleware('permission:appointment.view')->name('index');
    Route::get('/export', [AppointmentExportController::class, 'export'])->middleware('permission:appointment.view')->name('export');
    Route::get('/export/pdf', [AppointmentExportController::class, 'exportPdf'])->middleware('permission:appointment.view')->name('export.pdf');
    Route::post('/', [AppointmentController::class, 'store'])->middleware('permission:appointment.create')->name('store');
    Route::delete('/bulk', [AppointmentController::class, 'bulkDestroy'])->middleware('permission:appointment.delete')->name('bulk-destroy');
    Route::get('/{appointmentId}', [AppointmentController::class, 'show'])->middleware('permission:appointment.view')->name('show');
    Route::match(['put', 'patch'], '/{appointmentId}', [AppointmentController::class, 'update'])->middleware('permission:appointment.update')->name('update');
    Route::patch('/{appointmentId}/status', [AppointmentController::class, 'transitionStatus'])->middleware('permission:appointment.update,appointment.arrival')->name('transition-status');
    Route::delete('/{appointmentId}', [AppointmentController::class, 'destroy'])->middleware('permission:appointment.delete')->name('destroy');
});
