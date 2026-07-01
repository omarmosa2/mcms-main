<?php

use App\Http\Controllers\Payroll\PayrollController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('salaries')->name('salaries.')->group(function () {
    Route::get('/', [PayrollController::class, 'index'])->middleware('permission:salaries.view')->name('index');
    Route::get('/employee-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/employee-payments', [PayrollController::class, 'storeEmployeePayment'])->middleware('permission:salaries.pay')->name('employee-payments.store');
    Route::get('/doctor-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/doctor-payments', [PayrollController::class, 'storeDoctorPayment'])->middleware('permission:salaries.pay')->name('doctor-payments.store');
    Route::post('/beneficiaries/{type}/{id}/sham-cash-qr', [PayrollController::class, 'updateBeneficiaryShamCashQr'])
        ->middleware('permission:salaries.pay')
        ->name('beneficiaries.sham-cash-qr');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/employee-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/employee-payments', fn (Request $request, PayrollController $controller): JsonResponse|RedirectResponse => $controller->storeEmployeePayment($request))->middleware('permission:salaries.pay')->name('legacy.salaries.employee-payments.store');
    Route::get('/doctor-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/doctor-payments', fn (Request $request, PayrollController $controller): JsonResponse|RedirectResponse => $controller->storeDoctorPayment($request))->middleware('permission:salaries.pay')->name('legacy.salaries.doctor-payments.store');

    Route::get('/financial/employee-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/financial/employee-payments', fn (Request $request, PayrollController $controller): JsonResponse|RedirectResponse => $controller->storeEmployeePayment($request))->middleware('permission:salaries.pay')->name('legacy.financial.employee-payments.store');
    Route::get('/financial/doctor-payments', fn (): RedirectResponse => redirect()->route('salaries.index'))->middleware('permission:salaries.view');
    Route::post('/financial/doctor-payments', fn (Request $request, PayrollController $controller): JsonResponse|RedirectResponse => $controller->storeDoctorPayment($request))->middleware('permission:salaries.pay')->name('legacy.financial.doctor-payments.store');
});
