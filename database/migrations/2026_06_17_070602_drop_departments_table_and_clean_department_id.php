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
            if (! Schema::hasTable('departments')) {
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
            }

            return;
        }

        if (Schema::hasColumn('employees', 'department_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $foreignKeys = DB::select(
                    'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                    ['employees', 'department_id']
                );

                $fkNames = array_column($foreignKeys, 'CONSTRAINT_NAME');
                if (in_array('employees_department_id_foreign', $fkNames)) {
                    $table->dropForeign('employees_department_id_foreign');
                }

                if (Schema::hasIndex('employees', 'employees_clinic_department_idx')) {
                    $table->dropIndex('employees_clinic_department_idx');
                }

                $table->dropColumn('department_id');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->index(['clinic_id', 'department_id'], 'employees_clinic_department_idx');
            });
        }
    }
};
