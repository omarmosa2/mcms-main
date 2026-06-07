<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_appointment_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_profile_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->decimal('appointment_cost', 12, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('entitlement_amount', 12, 2);
            $table->string('status', 20)->default('unpaid');
            $table->date('appointment_date');
            $table->timestamps();

            $table->index(['clinic_id', 'doctor_profile_id', 'status'], 'entitlement_doctor_status_idx');
            $table->index(['clinic_id', 'appointment_date'], 'entitlement_clinic_date_idx');
            $table->unique(['clinic_id', 'appointment_id'], 'entitlement_appointment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_appointment_entitlements');
    }
};
