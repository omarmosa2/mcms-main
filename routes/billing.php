<?php

use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\InvoiceExportController;
use App\Http\Controllers\Billing\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:billing.view')->name('invoices.index');
    Route::get('/invoices/export', [InvoiceExportController::class, 'export'])->middleware('permission:billing.view')->name('invoices.export');
    Route::get('/invoices/export/pdf', [InvoiceExportController::class, 'exportPdf'])->middleware('permission:billing.view')->name('invoices.export.pdf');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:billing.generate')->name('invoices.store');
    Route::delete('/invoices/bulk', [InvoiceController::class, 'bulkDestroy'])->middleware('permission:billing.generate')->name('invoices.bulk-destroy');
    Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show'])->middleware('permission:billing.view')->name('invoices.show');
    Route::match(['put', 'patch'], '/invoices/{invoiceId}', [InvoiceController::class, 'update'])->middleware('permission:billing.generate')->name('invoices.update');
    Route::patch('/invoices/{invoiceId}/issue', [InvoiceController::class, 'issue'])->middleware('permission:billing.generate')->name('invoices.issue');
    Route::delete('/invoices/{invoiceId}', [InvoiceController::class, 'destroy'])->middleware('permission:billing.generate')->name('invoices.destroy');

    Route::post('/invoices/{invoiceId}/payments', [PaymentController::class, 'store'])->middleware('permission:payment.record')->name('payments.store');
    Route::patch('/payments/{paymentId}/refund', [PaymentController::class, 'refund'])->middleware('permission:payment.refund')->name('payments.refund');
});
