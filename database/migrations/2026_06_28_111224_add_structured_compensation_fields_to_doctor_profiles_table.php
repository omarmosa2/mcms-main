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
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE doctor_profiles MODIFY compensation_type VARCHAR(20) NULL');
        }

        DB::table('doctor_profiles')
            ->where('compensation_type', 'weekly_fixed')
            ->update(['compensation_type' => 'fixed_weekly']);

        DB::table('doctor_profiles')
            ->where('compensation_type', 'monthly_fixed')
            ->update(['compensation_type' => 'fixed_monthly']);

        Schema::table('doctor_profiles', function (Blueprint $table): void {
            $table->decimal('percentage_value', 5, 2)->nullable()->after('compensation_value');
            $table->decimal('fixed_weekly_amount', 12, 2)->nullable()->after('percentage_value');
            $table->decimal('fixed_monthly_amount', 12, 2)->nullable()->after('fixed_weekly_amount');
            $table->string('currency', 3)->default('SYP')->after('fixed_monthly_amount');
        });

        DB::table('doctor_profiles')
            ->where('compensation_type', 'percentage')
            ->whereNotNull('compensation_value')
            ->update(['percentage_value' => DB::raw('compensation_value')]);

        DB::table('doctor_profiles')
            ->where('compensation_type', 'fixed_weekly')
            ->whereNotNull('compensation_value')
            ->update(['fixed_weekly_amount' => DB::raw('compensation_value')]);

        DB::table('doctor_profiles')
            ->where('compensation_type', 'fixed_monthly')
            ->whereNotNull('compensation_value')
            ->update(['fixed_monthly_amount' => DB::raw('compensation_value')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('doctor_profiles')
            ->where('compensation_type', 'fixed_weekly')
            ->update([
                'compensation_type' => 'weekly_fixed',
                'compensation_value' => DB::raw('fixed_weekly_amount'),
            ]);

        DB::table('doctor_profiles')
            ->where('compensation_type', 'fixed_monthly')
            ->update([
                'compensation_type' => 'monthly_fixed',
                'compensation_value' => DB::raw('fixed_monthly_amount'),
            ]);

        DB::table('doctor_profiles')
            ->where('compensation_type', 'percentage')
            ->update(['compensation_value' => DB::raw('percentage_value')]);

        Schema::table('doctor_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'percentage_value',
                'fixed_weekly_amount',
                'fixed_monthly_amount',
                'currency',
            ]);
        });
    }
};
