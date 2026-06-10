<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Clinic Working Hours for Wednesday:\n";
$hours = App\Models\ClinicWorkingHour::where('day_of_week', 'wednesday')->where('is_active', true)->with('department')->get();
foreach ($hours as $h) {
    echo 'Department: ' . ($h->department?->name ?? 'N/A') . ' - ' . $h->start_time . '-' . $h->end_time . "\n";
}

echo "\nDepartments:\n";
$departments = App\Models\Department::where('is_active', true)->get();
foreach ($departments as $d) {
    echo $d->id . ' - ' . $d->name . "\n";
}
