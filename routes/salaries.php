<?php

use App\Http\Controllers\Payroll\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('salaries')->name('salaries.')->group(function () {
    Route::get('/', [PayrollController::class, 'index'])->middleware('permission:salaries.view')->name('index');
    Route::post('/employee-payments', [PayrollController::class, 'storeEmployeePayment'])->middleware('permission:salaries.pay')->name('employee-payments.store');
    Route::post('/doctor-payments', [PayrollController::class, 'storeDoctorPayment'])->middleware('permission:salaries.pay')->name('doctor-payments.store');
});
