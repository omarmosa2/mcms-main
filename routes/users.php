<?php

use App\Http\Controllers\Security\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view')->name('index');
    Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create')->name('store');
    Route::delete('/bulk', [UserController::class, 'bulkDestroy'])->middleware('permission:users.delete')->name('bulk-destroy');
    Route::match(['put', 'patch'], '/{userId}', [UserController::class, 'update'])->middleware('permission:users.update')->name('update');
    Route::delete('/{userId}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('destroy');
});
