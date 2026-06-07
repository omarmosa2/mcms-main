<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE patients SET file_number = id WHERE file_number REGEXP '[^0-9]' OR file_number = '' OR file_number IS NULL");
        } else {
            DB::statement("UPDATE patients SET file_number = id WHERE CAST(file_number AS INTEGER) = 0 OR file_number = '' OR file_number IS NULL");
        }

        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique('patients_clinic_id_file_number_unique');
            });
        } catch (Throwable) {
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedInteger('file_number')->change();
            $table->unique(['clinic_id', 'file_number']);
        });
    }

    public function down(): void
    {
        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique('patients_clinic_id_file_number_unique');
            });
        } catch (Throwable) {
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->string('file_number')->change();
            $table->unique(['clinic_id', 'file_number']);
        });
    }
};
