<?php

use App\Http\Controllers\Cashbox\CashboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('cashbox')->name('cashbox.')->group(function () {
    Route::get('/', [CashboxController::class, 'index'])->middleware('permission:cashbox.view')->name('index');
    Route::get('/{cashboxId}', [CashboxController::class, 'show'])->middleware('permission:cashbox.view')->name('show');
    Route::post('/', [CashboxController::class, 'store'])->middleware('permission:cashbox.open')->name('store');
    Route::match(['put', 'patch'], '/{cashboxId}', [CashboxController::class, 'update'])->middleware('permission:cashbox.open')->name('update');
    Route::post('/{cashboxId}/close', [CashboxController::class, 'close'])->middleware('permission:cashbox.close')->name('close');
    Route::delete('/bulk', [CashboxController::class, 'bulkDestroy'])->middleware('permission:cashbox.open')->name('bulk-destroy');
    Route::delete('/{cashboxId}', [CashboxController::class, 'destroy'])->middleware('permission:cashbox.open')->name('destroy');
});
