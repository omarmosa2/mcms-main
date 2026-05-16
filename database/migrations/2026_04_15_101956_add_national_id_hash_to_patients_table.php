<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('national_id_hash', 64)->nullable()->after('national_id');
            $table->index(['clinic_id', 'national_id_hash'], 'patients_clinic_national_id_hash_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_clinic_national_id_hash_idx');
            $table->dropColumn('national_id_hash');
        });
    }
};
