<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('clinics')
            ->whereNull('code')
            ->orderBy('id')
            ->each(function (object $clinic): void {
                DB::table('clinics')
                    ->where('id', $clinic->id)
                    ->update(['code' => 'CLINIC-'.$clinic->id]);
            });

        Schema::table('clinics', function (Blueprint $table): void {
            $table->string('code', 50)->nullable(false)->change();
        });
    }
};
