<?php

use App\Http\Controllers\Rbac\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('roles')->name('roles.')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('index');
    Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('store');
    Route::match(['put', 'patch'], '/{roleId}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('update');
    Route::delete('/{roleId}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('destroy');
});
