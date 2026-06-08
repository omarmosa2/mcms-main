<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration())
        && (bool) config('security.public_registration_enabled', false),
])->name('home');

use App\Http\Controllers\DashboardController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/portal.php';
require __DIR__.'/doctor.php';
require __DIR__.'/settings.php';
require __DIR__.'/admin-settings.php';
require __DIR__.'/notifications.php';
require __DIR__.'/help.php';
require __DIR__.'/accounts.php';
require __DIR__.'/patients.php';
require __DIR__.'/departments.php';
require __DIR__.'/doctors.php';
require __DIR__.'/employees.php';
require __DIR__.'/doctor-schedules.php';
require __DIR__.'/appointments.php';
require __DIR__.'/billing.php';
require __DIR__.'/expenses.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/salaries.php';
require __DIR__.'/cashbox.php';
require __DIR__.'/reports.php';
require __DIR__.'/pharmacy.php';
require __DIR__.'/lab.php';
require __DIR__.'/radiology.php';
require __DIR__.'/financial.php';
require __DIR__.'/inventory.php';
require __DIR__.'/diagnostics.php';
require __DIR__.'/monitoring.php';
require __DIR__.'/api-docs.php';
require __DIR__.'/trash.php';
require __DIR__.'/medical-records.php';
