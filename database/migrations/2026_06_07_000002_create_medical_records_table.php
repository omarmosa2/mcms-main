<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('record_number')->unique();
            $table->string('clinic_type', 50)->nullable();
            $table->json('form_data')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('primary_diagnosis')->nullable();
            $table->text('secondary_diagnosis')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('examination')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('visit_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'patient_id', 'created_at']);
            $table->index(['clinic_id', 'doctor_id']);
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'clinic_type']);
            $table->index(['clinic_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
