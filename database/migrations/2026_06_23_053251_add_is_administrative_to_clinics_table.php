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
        Schema::table('clinics', function (Blueprint $table): void {
            $table->boolean('is_administrative')->default(false)->after('is_active');
            $table->index('is_administrative');
        });

        DB::table('clinics')
            ->where('code', 'ADMIN001')
            ->update(['is_administrative' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropIndex(['is_administrative']);
            $table->dropColumn('is_administrative');
        });
    }
};
