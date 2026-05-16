<?php

use App\Http\Controllers\Expenses\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses.view')->name('index');
    Route::get('/{expenseId}', [ExpenseController::class, 'show'])->middleware('permission:expenses.view')->name('show');
    Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses.create')->name('store');
    Route::delete('/bulk', [ExpenseController::class, 'bulkDestroy'])->middleware('permission:expenses.delete')->name('bulk-destroy');
    Route::post('/{expenseId}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expenses.approve')->name('approve');
    Route::post('/{expenseId}/reject', [ExpenseController::class, 'reject'])->middleware('permission:expenses.approve')->name('reject');
    Route::match(['put', 'patch'], '/{expenseId}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update')->name('update');
    Route::delete('/{expenseId}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete')->name('destroy');
});
