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
        Schema::table('patient_card_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_card_visits', 'appointment_id')) {
                $table->foreignId('appointment_id')->nullable()->nullOnDelete()->after('patient_id');
            }

            if (! Schema::hasColumn('patient_card_visits', 'visit_time')) {
                $table->time('visit_time')->nullable()->after('visit_date');
            }
        });

        $indexExists = DB::select("SHOW INDEX FROM patient_card_visits WHERE Key_name = 'unique_appointment_id'");

        if ($indexExists === []) {
            DB::statement('CREATE UNIQUE INDEX unique_appointment_id ON patient_card_visits (appointment_id, clinic_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_card_visits', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn(['appointment_id', 'visit_time']);
        });
    }
};
