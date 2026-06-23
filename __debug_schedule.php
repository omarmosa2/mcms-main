<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DoctorSchedule;
use App\Models\User;

$u = User::find(1);
echo 'user1 clinic_id=' . ($u?->clinic_id ?? 'NULL') . PHP_EOL;

$auth = app('auth');
$guard = $auth->guard('web');
$userProp = new ReflectionProperty($guard, 'user');
$userProp->setAccessible(true);
$userProp->setValue($guard, $u);

$schedule = DoctorSchedule::query()
    ->withoutGlobalScope('clinic')
    ->with('doctorProfile.user:id,name')
    ->first();

echo 'schedule id=' . $schedule->id . PHP_EOL;
echo 'doctorProfile is null? ' . ($schedule->doctorProfile === null ? 'yes' : 'no') . PHP_EOL;
if ($schedule->doctorProfile) {
    echo 'profile_id=' . $schedule->doctorProfile->id . ' user_id=' . ($schedule->doctorProfile->user_id ?? 'NULL') . PHP_EOL;
}