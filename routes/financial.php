<?php

use App\Http\Controllers\Financial\FinancialController;
use App\Http\Controllers\Financial\InstallmentController;
use App\Http\Controllers\Financial\PaymentPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('financial', [FinancialController::class, 'index'])
        ->middleware('permission:billing.view,billing.generate,payment.record,payment.refund,accounts.view,expenses.view,cashbox.view,reports.financial,salaries.view')
        ->name('financial.index');

    Route::prefix('payment-plans')->name('payment-plans.')->group(function (): void {
        Route::get('/', [PaymentPlanController::class, 'index'])->name('index');
        Route::post('/', [PaymentPlanController::class, 'store'])->name('store');
        Route::post('{plan}/apply', [PaymentPlanController::class, 'apply'])->name('apply');
    });

    Route::prefix('installments')->name('installments.')->group(function (): void {
        Route::get('/', [PaymentPlanController::class, 'installments'])->name('index');
        Route::post('{installment}/pay', [InstallmentController::class, 'pay'])->name('pay');
    });
});
