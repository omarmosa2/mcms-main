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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->unique()->constrained('queue_entries')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visit_number');
            $table->enum('status', ['started', 'in_progress', 'completed'])->default('started');
            $table->dateTime('started_at');
            $table->dateTime('in_progress_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->longText('clinical_notes')->nullable();
            $table->longText('diagnosis_notes')->nullable();
            $table->longText('treatment_plan')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'visit_number']);
            $table->index(['clinic_id', 'status', 'started_at']);
            $table->index(['clinic_id', 'patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
