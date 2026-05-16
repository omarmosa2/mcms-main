<?php

use App\Http\Controllers\Departments\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('departments')->name('departments.')->group(function () {
    Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:department.view')->name('index');
    Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:department.create')->name('store');
    Route::delete('/bulk', [DepartmentController::class, 'bulkDestroy'])->middleware('permission:department.delete')->name('bulk-destroy');
    Route::get('/{departmentId}', [DepartmentController::class, 'show'])->middleware('permission:department.view')->name('show');
    Route::match(['put', 'patch'], '/{departmentId}', [DepartmentController::class, 'update'])->middleware('permission:department.update')->name('update');
    Route::delete('/{departmentId}', [DepartmentController::class, 'destroy'])->middleware('permission:department.delete')->name('destroy');
});
