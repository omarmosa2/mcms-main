<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('doctor_schedules')
            ->select(['clinic_id', 'doctor_id', 'day_of_week', 'start_time', 'end_time'])
            ->groupBy(['clinic_id', 'doctor_id', 'day_of_week', 'start_time', 'end_time'])
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('clinic_id')
            ->each(function (object $duplicate): void {
                $ids = DB::table('doctor_schedules')
                    ->where('clinic_id', $duplicate->clinic_id)
                    ->where('doctor_id', $duplicate->doctor_id)
                    ->where('day_of_week', $duplicate->day_of_week)
                    ->where('start_time', $duplicate->start_time)
                    ->where('end_time', $duplicate->end_time)
                    ->orderBy('id')
                    ->pluck('id');

                DB::table('doctor_schedules')
                    ->whereIn('id', $ids->skip(1))
                    ->delete();
            });

        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->unique(
                ['clinic_id', 'doctor_id', 'day_of_week', 'start_time', 'end_time'],
                'doctor_schedules_unique_period',
            );
            $table->index(
                ['clinic_id', 'doctor_id', 'day_of_week', 'is_available'],
                'doctor_schedules_availability_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->dropUnique('doctor_schedules_unique_period');
            $table->dropIndex('doctor_schedules_availability_index');
        });
    }
};
