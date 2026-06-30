<?php

use App\Http\Controllers\Employees\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.view')->name('index');
    Route::get('/export', [EmployeeController::class, 'export'])->middleware('permission:employees.view')->name('export');
    Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.create')->name('store');
    Route::get('/{employeeId}', [EmployeeController::class, 'show'])->middleware('permission:employees.view')->whereNumber('employeeId')->name('show');
    Route::match(['put', 'patch'], '/{employeeId}', [EmployeeController::class, 'update'])->middleware('permission:employees.update')->name('update');
    Route::delete('/{employeeId}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete')->name('destroy');
});
