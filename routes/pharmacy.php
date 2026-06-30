<?php

use App\Http\Controllers\Pharmacy\DrugController;
use App\Http\Controllers\Pharmacy\InventoryAlertController;
use App\Http\Controllers\Pharmacy\PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\PrescriptionController;
use App\Http\Controllers\Pharmacy\PurchaseOrderController;
use App\Http\Controllers\Pharmacy\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('pharmacy')->name('pharmacy.')->group(function () {
    Route::get('/', [PharmacyDashboardController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('dashboard');

    Route::get('/drugs', [DrugController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('drugs.index');
    Route::post('/drugs', [DrugController::class, 'store'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('drugs.store');
    Route::put('/drugs/{drugId}', [DrugController::class, 'update'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('drugs.update');
    Route::delete('/drugs/{drugId}', [DrugController::class, 'destroy'])->middleware('role:admin,super_admin,clinic_admin')->name('drugs.destroy');
    Route::get('/drugs/{drugId}/batches', [DrugController::class, 'batches'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('drugs.batches');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('prescriptions.index');
    Route::get('/prescriptions/{prescriptionId}', [PrescriptionController::class, 'show'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('prescriptions.show');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('prescriptions.store');
    Route::patch('/prescriptions/{prescriptionId}/status', [PrescriptionController::class, 'updateStatus'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('prescriptions.status');
    Route::post('/prescriptions/{prescriptionId}/dispense', [PrescriptionController::class, 'dispense'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('prescriptions.dispense');

    Route::get('/stock-movements', [PrescriptionController::class, 'stockMovements'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('stock-movements');

    Route::get('/alerts', [InventoryAlertController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('alerts.index');
    Route::post('/alerts/{alertId}/resolve', [InventoryAlertController::class, 'resolve'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('alerts.resolve');

    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('role:admin,super_admin,clinic_admin')->name('suppliers.store');
    Route::put('/suppliers/{supplierId}', [SupplierController::class, 'update'])->middleware('role:admin,super_admin,clinic_admin')->name('suppliers.update');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('role:admin,super_admin,clinic_admin')->name('purchase-orders.store');
    Route::post('/purchase-orders/{purchaseOrderId}/receive', [PurchaseOrderController::class, 'receive'])->middleware('role:admin,super_admin,clinic_admin,pharmacy')->name('purchase-orders.receive');
});
