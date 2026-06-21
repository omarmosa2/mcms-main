<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'clinic_working_hours';
        $driver = DB::getDriverName();
        $isMysql = $driver === 'mysql';

        if ($isMysql) {
            // MySQL: Drop ALL foreign key constraints first, then indexes
            $fks = $this->getForeignKeys($table);
            foreach ($fks as $fk) {
                Schema::table($table, function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk);
                });
            }

            $indexes = $this->getIndexesOnColumn($table, 'department_id');
            foreach ($indexes as $indexName) {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }

            if (Schema::hasColumn($table, 'department_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('department_id');
                });
            }
        } else {
            // SQLite: Recreate table without department_id (can't drop FK columns directly)
            if (Schema::hasColumn($table, 'department_id')) {
                $rows = DB::table($table)->select('*')->get();

                Schema::dropIfExists('clinic_working_hours_backup');
                DB::statement('CREATE TABLE clinic_working_hours_backup AS SELECT id, clinic_id, day_of_week, is_active, start_time, end_time, created_at, updated_at FROM clinic_working_hours');
                Schema::dropIfExists($table);

                Schema::create($table, function (Blueprint $schema) {
                    $schema->id();
                    $schema->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                    $schema->tinyInteger('day_of_week');
                    $schema->boolean('is_active')->default(false);
                    $schema->time('start_time')->nullable();
                    $schema->time('end_time')->nullable();
                    $schema->timestamps();
                });

                DB::statement('INSERT INTO clinic_working_hours (id, clinic_id, day_of_week, is_active, start_time, end_time, created_at, updated_at) SELECT id, clinic_id, day_of_week, is_active, start_time, end_time, created_at, updated_at FROM clinic_working_hours_backup');
                Schema::dropIfExists('clinic_working_hours_backup');
            }
        }

        // Delete rows with null clinic_id
        DB::table($table)->whereNull('clinic_id')->delete();

        // Make clinic_id NOT NULL and add foreign key
        Schema::table($table, function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable(false)->change();
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
        });

        // Convert day_of_week from string to numeric (only needed for MySQL; SQLite already handled above)
        if ($isMysql) {
            $stringToNumeric = [
                'saturday' => 6, 'sunday' => 0, 'monday' => 1,
                'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5,
            ];

            foreach ($stringToNumeric as $dayName => $dayNumber) {
                DB::table($table)->where('day_of_week', $dayName)->update(['day_of_week' => $dayNumber]);
            }

            Schema::table($table, function (Blueprint $table) {
                $table->tinyInteger('day_of_week')->change();
            });
        }

        // Add new constraints (only if they don't already exist)
        if (! Schema::hasIndex($table, 'clinic_working_hours_clinic_id_day_of_week_unique')) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->unique(['clinic_id', 'day_of_week']);
            });
        }
        if (! Schema::hasIndex($table, 'clinic_working_hours_clinic_id_is_active_day_of_week_index')) {
            Schema::table($table, function (Blueprint $schema) {
                $schema->index(['clinic_id', 'is_active', 'day_of_week']);
            });
        }
    }

    public function down(): void
    {
        $table = 'clinic_working_hours';

        Schema::table($table, function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropUnique(['clinic_id', 'day_of_week']);
            $table->dropIndex(['clinic_id', 'is_active', 'day_of_week']);
        });

        Schema::table($table, function (Blueprint $table) {
            $table->string('day_of_week')->change();
        });

        $numericToString = [
            0 => 'sunday', 1 => 'monday', 2 => 'tuesday',
            3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday',
        ];

        foreach ($numericToString as $dayNumber => $dayName) {
            DB::table($table)->where('day_of_week', $dayNumber)->update(['day_of_week' => $dayName]);
        }

        Schema::table($table, function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->change();
        });

        Schema::table($table, function (Blueprint $table) {
            $table->foreignId('department_id')->after('clinic_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['department_id', 'day_of_week']);
            $table->index(['department_id', 'is_active', 'day_of_week']);
        });
    }

    private function getForeignKeys(string $table): array
    {
        $results = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, DB::getDatabaseName()]
        );

        return array_unique(array_map(fn ($row) => $row->CONSTRAINT_NAME, $results));
    }

    private function getIndexesOnColumn(string $table, string $column): array
    {
        $results = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ? AND COLUMN_NAME = ?',
            [$table, DB::getDatabaseName(), $column]
        );

        return array_unique(array_map(fn ($row) => $row->INDEX_NAME, $results));
    }
};
