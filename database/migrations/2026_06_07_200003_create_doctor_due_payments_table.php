<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_due_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_monthly_due_id')->constrained('doctor_monthly_dues')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('salary_month', 7);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'salary_month'], 'doc_pay_period_idx');
            $table->index(['doctor_monthly_due_id'], 'doc_pay_monthly_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_due_payments');
    }
};
