<?php

use App\Http\Controllers\DailySchedule\DailyScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('daily-schedule')->name('daily-schedule.')->group(function () {
    Route::get('/', [DailyScheduleController::class, 'index'])->middleware('permission:doctor_schedule.view')->name('index');
    Route::get('/display', [DailyScheduleController::class, 'display'])->middleware('permission:doctor_schedule.view')->name('display');
});
