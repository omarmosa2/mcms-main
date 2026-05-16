<?php

use App\Http\Controllers\Accounting\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->middleware('permission:accounts.view')->name('index');
    Route::post('/', [AccountController::class, 'store'])->middleware('permission:accounts.create')->name('store');
    Route::match(['put', 'patch'], '/{accountId}', [AccountController::class, 'update'])->middleware('permission:accounts.update')->name('update');
    Route::delete('/{accountId}', [AccountController::class, 'destroy'])->middleware('permission:accounts.delete')->name('destroy');
});
