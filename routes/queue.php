<?php

use App\Http\Controllers\Queue\QueueEntryController;
use App\Http\Controllers\Queue\QueueEntryExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('queue')->name('queue.')->group(function () {
    Route::get('/', [QueueEntryController::class, 'index'])->middleware('permission:queue.view')->name('index');
    Route::get('/export', [QueueEntryExportController::class, 'export'])->middleware('permission:queue.view')->name('export');
    Route::get('/export/pdf', [QueueEntryExportController::class, 'exportPdf'])->middleware('permission:queue.view')->name('export.pdf');
    Route::post('/', [QueueEntryController::class, 'store'])->middleware('permission:queue.manage')->name('store');
    Route::post('/call-next', [QueueEntryController::class, 'callNext'])->middleware('permission:queue.call_next')->name('call-next');
    Route::delete('/bulk', [QueueEntryController::class, 'bulkDestroy'])->middleware('permission:queue.manage')->name('bulk-destroy');
    Route::get('/{queueEntryId}', [QueueEntryController::class, 'show'])->middleware('permission:queue.view')->name('show');
    Route::patch('/{queueEntryId}/status', [QueueEntryController::class, 'updateStatus'])->middleware('permission:queue.manage')->name('update-status');
    Route::delete('/{queueEntryId}', [QueueEntryController::class, 'destroy'])->middleware('permission:queue.manage')->name('destroy');
});
