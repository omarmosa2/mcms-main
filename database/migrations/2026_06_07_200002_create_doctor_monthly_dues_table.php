<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_monthly_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->string('salary_month', 7);
            $table->string('payment_type', 20);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('fixed_weekly_amount', 12, 2)->nullable();
            $table->decimal('fixed_monthly_amount', 12, 2)->nullable();
            $table->decimal('visits_total_amount', 12, 2)->default(0);
            $table->decimal('deductions_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2);
            $table->string('status', 20)->default('unpaid');
            $table->timestamps();

            $table->unique(['clinic_id', 'doctor_id', 'salary_month'], 'doc_monthly_unique_idx');
            $table->index(['clinic_id', 'salary_month'], 'doc_monthly_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_monthly_dues');
    }
};
