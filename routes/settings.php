<?php

use App\Http\Controllers\Settings\ComplianceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SecurityPolicyController;
use App\Http\Controllers\Settings\UserInvitationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::get('settings/security/policies', [SecurityPolicyController::class, 'show'])
        ->name('security-policies.show');
    Route::put('settings/security/policies', [SecurityPolicyController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('security-policies.update');
    Route::post('settings/security/invitations', [UserInvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('security-invitations.store');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    Route::get('settings/compliance', [ComplianceController::class, 'index'])
        ->middleware('permission:reports.view,reports.financial')
        ->name('compliance.index');
});
