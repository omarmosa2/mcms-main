<?php

use App\Http\Controllers\Trash\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('trash')->name('trash.')->group(function () {
    Route::get('/', [TrashController::class, 'index'])->name('index');
    Route::post('/{type}/{id}/restore', [TrashController::class, 'restore'])->name('restore');
    Route::delete('/{type}/{id}/force', [TrashController::class, 'forceDelete'])->name('force-delete');
    Route::delete('/{type}/empty', [TrashController::class, 'emptyTrash'])->name('empty');
});
