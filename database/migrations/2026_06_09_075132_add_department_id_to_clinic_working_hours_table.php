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
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $constraints = $this->getMySQLTableConstraints('clinic_working_hours');

            if (in_array('clinic_working_hours_clinic_id_foreign', $constraints)) {
                Schema::table('clinic_working_hours', function (Blueprint $table) {
                    $table->dropForeign('clinic_working_hours_clinic_id_foreign');
                });
            }

            Schema::table('clinic_working_hours', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'is_active', 'day_of_week']);
            });
        }

        Schema::table('clinic_working_hours', function (Blueprint $table) {
            $table->foreignId('department_id')->after('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->change();
            $table->unique(['department_id', 'day_of_week']);
            $table->index(['department_id', 'is_active', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('clinic_working_hours', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique(['department_id', 'day_of_week']);
            $table->dropIndex(['department_id', 'is_active', 'day_of_week']);
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('clinic_working_hours', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable(false)->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->unique(['clinic_id', 'day_of_week']);
            $table->index(['clinic_id', 'is_active', 'day_of_week']);
        });
    }

    private function getMySQLTableConstraints(string $table): array
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?',
            [$table, DB::getDatabaseName()]
        );

        return array_map(fn ($row) => $row->CONSTRAINT_NAME, $constraints);
    }
};
