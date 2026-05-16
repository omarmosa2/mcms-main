<?php

use App\Http\Controllers\Salaries\SalaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('salaries')->name('salaries.')->group(function () {
    Route::get('/', [SalaryController::class, 'index'])->middleware('permission:salaries.view')->name('index');
    Route::post('/', [SalaryController::class, 'store'])->middleware('permission:salaries.create')->name('store');
    Route::match(['put', 'patch'], '/{salaryId}', [SalaryController::class, 'update'])->middleware('permission:salaries.update')->name('update');
    Route::delete('/{salaryId}', [SalaryController::class, 'destroy'])->middleware('permission:salaries.delete')->name('destroy');
    Route::post('/{salaryId}/approve', [SalaryController::class, 'approve'])->middleware('permission:salaries.approve')->name('approve');
    Route::post('/{salaryId}/pay', [SalaryController::class, 'pay'])->middleware('permission:salaries.pay')->name('pay');
});
