<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('doctor_schedules', 'doctor_profile_id')) {
            Schema::table('doctor_schedules', function (Blueprint $table): void {
                $table->unsignedBigInteger('doctor_profile_id')->nullable()->after('clinic_id');
            });
        }

        DB::table('doctor_schedules')->select(['id', 'clinic_id', 'doctor_id'])->orderBy('id')->each(function (object $schedule): void {
            $doctorProfileId = DB::table('doctor_profiles')
                ->where('clinic_id', $schedule->clinic_id)
                ->where('user_id', $schedule->doctor_id)
                ->value('id');

            if ($doctorProfileId === null) {
                throw new RuntimeException("Doctor schedule {$schedule->id} has no matching doctor profile.");
            }

            DB::table('doctor_schedules')->where('id', $schedule->id)->update(['doctor_profile_id' => $doctorProfileId]);
        });

        try {
            Schema::table('doctor_schedules', function (Blueprint $table): void {
                $table->dropForeign(['doctor_id']);
            });
        } catch (Throwable) {
            // A previous interrupted migration may already have dropped it.
        }

        Schema::table('doctor_schedules', function (Blueprint $table): void {
            if (! Schema::hasIndex('doctor_schedules', 'doctor_schedules_clinic_id_index')) {
                $table->index('clinic_id', 'doctor_schedules_clinic_id_index');
            }
            if (Schema::hasIndex('doctor_schedules', 'doctor_schedules_unique_period')) {
                $table->dropUnique('doctor_schedules_unique_period');
            }
            if (Schema::hasIndex('doctor_schedules', 'doctor_schedules_availability_index')) {
                $table->dropIndex('doctor_schedules_availability_index');
            }
            if (Schema::hasIndex('doctor_schedules', 'doctor_schedules_clinic_id_doctor_id_index')) {
                $table->dropIndex(['clinic_id', 'doctor_id']);
            }
            $table->dropColumn('doctor_id');
        });

        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->renameColumn('doctor_profile_id', 'doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctor_profiles')->cascadeOnDelete();
            $table->unique(['clinic_id', 'doctor_id', 'day_of_week', 'start_time', 'end_time'], 'doctor_schedules_unique_period');
            $table->index(['clinic_id', 'doctor_id', 'day_of_week', 'is_available'], 'doctor_schedules_availability_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->unsignedBigInteger('doctor_user_id')->nullable()->after('clinic_id');
        });

        DB::table('doctor_schedules')->select(['id', 'doctor_id'])->orderBy('id')->each(function (object $schedule): void {
            $doctorUserId = DB::table('doctor_profiles')->where('id', $schedule->doctor_id)->value('user_id');

            if ($doctorUserId === null) {
                throw new RuntimeException("Doctor schedule {$schedule->id} has no matching doctor user.");
            }

            DB::table('doctor_schedules')->where('id', $schedule->id)->update(['doctor_user_id' => $doctorUserId]);
        });

        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->dropUnique('doctor_schedules_unique_period');
            $table->dropIndex('doctor_schedules_availability_index');
            $table->dropForeign(['doctor_id']);
            $table->dropColumn('doctor_id');
        });

        Schema::table('doctor_schedules', function (Blueprint $table): void {
            $table->renameColumn('doctor_user_id', 'doctor_id');
            $table->foreign('doctor_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['clinic_id', 'doctor_id']);
            $table->unique(['clinic_id', 'doctor_id', 'day_of_week', 'start_time', 'end_time'], 'doctor_schedules_unique_period');
            $table->index(['clinic_id', 'doctor_id', 'day_of_week', 'is_available'], 'doctor_schedules_availability_index');
        });
    }
};
