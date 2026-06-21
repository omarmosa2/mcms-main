<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->string('clinic_type', 50)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('clinic_type');
        });
    }
};
