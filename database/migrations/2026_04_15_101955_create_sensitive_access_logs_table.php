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
        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'resource_type', 'accessed_at'], 'sensitive_access_logs_clinic_resource_idx');
            $table->index(['clinic_id', 'patient_id', 'accessed_at'], 'sensitive_access_logs_clinic_patient_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensitive_access_logs');
    }
};
