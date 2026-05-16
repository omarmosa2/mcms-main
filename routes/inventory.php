<?php

use App\Http\Controllers\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::post('adjust-stock', [InventoryController::class, 'adjustStock'])->name('adjust-stock');
        Route::post('batches', [InventoryController::class, 'createBatch'])->name('batches.store');
        Route::get('batches', [InventoryController::class, 'batches'])->name('batches.index');
        Route::post('batches/consume', [InventoryController::class, 'consumeBatch'])->name('batches.consume');
        Route::post('returns', [InventoryController::class, 'returnStock'])->name('returns.store');
        Route::get('returns', [InventoryController::class, 'returns'])->name('returns.index');
        Route::get('adjustments', [InventoryController::class, 'adjustments'])->name('adjustments.index');
    });
});
