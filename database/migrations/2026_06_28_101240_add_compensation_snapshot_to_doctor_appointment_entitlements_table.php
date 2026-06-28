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
        Schema::table('doctor_appointment_entitlements', function (Blueprint $table) {
            $table->string('compensation_type', 20)
                ->default('percentage')
                ->after('entitlement_amount');
            $table->decimal('compensation_value', 12, 2)
                ->nullable()
                ->after('compensation_type');

            $table->index(['clinic_id', 'compensation_type'], 'entitlement_compensation_type_idx');
        });

        DB::table('doctor_appointment_entitlements')
            ->whereNull('compensation_type')
            ->update(['compensation_type' => 'percentage']);

        DB::table('doctor_appointment_entitlements')
            ->where('status', 'unpaid')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('doctor_appointment_entitlements')
            ->where('status', 'pending')
            ->update(['status' => 'unpaid']);

        Schema::table('doctor_appointment_entitlements', function (Blueprint $table) {
            $table->dropIndex('entitlement_compensation_type_idx');
            $table->dropColumn(['compensation_type', 'compensation_value']);
        });
    }
};
