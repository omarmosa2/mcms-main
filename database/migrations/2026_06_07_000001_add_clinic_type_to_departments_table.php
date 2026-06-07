<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('clinic_type', 50)->nullable()->after('name');
            $table->index(['clinic_id', 'clinic_type']);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'clinic_type']);
            $table->dropColumn('clinic_type');
        });
    }
};
