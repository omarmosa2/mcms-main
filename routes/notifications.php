<?php

use App\Http\Controllers\Settings\NotificationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('settings')->group(function () {
    Route::get('notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.edit');
    Route::put('notifications', [NotificationSettingsController::class, 'update'])->name('notifications.update');
});
