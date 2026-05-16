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
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('license_number', 100)->nullable();
            $table->string('specialty', 150);
            $table->unsignedSmallInteger('consultation_duration_minutes')->default(30);
            $table->enum('status', ['active', 'on_leave', 'inactive'])->default('active');
            $table->json('work_schedule')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id']);
            $table->unique(['clinic_id', 'license_number']);
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'department_id']);
            $table->index(['clinic_id', 'specialty']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
