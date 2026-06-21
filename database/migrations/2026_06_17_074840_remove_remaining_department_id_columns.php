<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $tablesToClean = [
            'doctor_profiles',
            'patient_card_visits',
            'medical_records',
            'doctor_leaves',
            'clinic_working_hours',
        ];

        foreach ($tablesToClean as $table) {
            if (Schema::hasColumn($table, 'department_id')) {
                $indexes = Schema::getIndexes($table);
                foreach ($indexes as $index) {
                    if (is_array($index['columns']) && in_array('department_id', $index['columns'], true)) {
                        Schema::table($table, function (Blueprint $t) use ($index) {
                            $t->dropIndex($index['name']);
                        });
                    }
                }

                $foreignKeys = DB::select(
                    'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                    [$table, 'department_id']
                );

                $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');

                Schema::table($table, function (Blueprint $table) use ($fkNames) {
                    if (! empty($fkNames)) {
                        $table->dropForeign($fkNames);
                    }
                    $table->dropColumn('department_id');
                });
            }
        }

        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['clinic_id', 'name']);
            $table->unique(['clinic_id', 'code']);
        });

        $tablesToRestore = [
            'doctor_profiles',
            'patient_card_visits',
            'medical_records',
            'doctor_leaves',
            'clinic_working_hours',
        ];

        foreach ($tablesToRestore as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            });
        }
    }
};
