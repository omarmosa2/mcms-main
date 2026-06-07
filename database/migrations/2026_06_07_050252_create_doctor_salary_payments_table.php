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
        Schema::create('doctor_salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_profile_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2);
            $table->string('payment_method')->nullable();
            $table->date('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'period_start', 'period_end'], 'doctor_payments_clinic_period_idx');
            $table->index(['clinic_id', 'doctor_profile_id'], 'doctor_payments_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_salary_payments');
    }
};
