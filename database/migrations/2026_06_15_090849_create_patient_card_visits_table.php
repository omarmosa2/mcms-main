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
        Schema::create('patient_card_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('visit_date');
            $table->text('visit_reason')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('new_symptoms')->nullable();
            $table->text('medical_or_surgical_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('prescribed_treatment_or_referral')->nullable();
            $table->string('signature')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'patient_id', 'visit_date']);
            $table->index(['clinic_id', 'doctor_id']);
            $table->index(['clinic_id', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_card_visits');
    }
};
