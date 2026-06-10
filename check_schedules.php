<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schedules = App\Models\DoctorSchedule::with('doctor')->where('is_available', true)->get();

echo "Doctor Schedules:\n";
foreach ($schedules as $s) {
    echo $s->id . ' - Doctor: ' . $s->doctor_id . ' - Day: ' . $s->day_of_week . ' - ' . $s->start_time . '-' . $s->end_time . "\n";
}

echo "\nDoctor Profiles:\n";
$profiles = App\Models\DoctorProfile::with('user')->where('status', 'active')->get();
foreach ($profiles as $p) {
    echo $p->id . ' - User: ' . $p->user_id . ' - Department: ' . $p->department_id . ' - ' . ($p->user?->name ?? 'N/A') . "\n";
}
