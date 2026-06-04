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
        Schema::table('doctor_profiles', function (Blueprint $table): void {
            $table->string('gender', 20)->nullable()->after('department_id');
            $table->string('phone', 50)->nullable()->after('gender');
            $table->string('compensation_type', 20)->nullable()->after('status');
            $table->decimal('compensation_value', 12, 2)->nullable()->after('compensation_type');
            $table->index(['clinic_id', 'compensation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table): void {
            $table->dropIndex(['clinic_id', 'compensation_type']);
            $table->dropColumn([
                'gender',
                'phone',
                'compensation_type',
                'compensation_value',
            ]);
        });

    }
};
