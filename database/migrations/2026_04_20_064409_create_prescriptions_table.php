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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prescription_number');
            $table->enum('status', ['draft', 'issued', 'dispensed', 'canceled'])->default('draft');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('dispensed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'prescription_number']);
            $table->index(['clinic_id', 'status', 'issued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
