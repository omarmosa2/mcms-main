<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prescriptions', 'sent_to_pharmacy_at')) {
            return;
        }

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'status', 'issued_at']);
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->string('status_temp')->nullable();
        });

        DB::statement('UPDATE prescriptions SET status_temp = status');

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->string('status')->default('draft');
            $table->dateTime('sent_to_pharmacy_at')->nullable();
            $table->unsignedInteger('dispensed_by')->nullable();
        });

        DB::statement('UPDATE prescriptions SET status = status_temp');

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropColumn('status_temp');
            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('prescriptions', 'sent_to_pharmacy_at')) {
                $table->dropColumn(['sent_to_pharmacy_at', 'dispensed_by']);
            }
        });
    }
};
