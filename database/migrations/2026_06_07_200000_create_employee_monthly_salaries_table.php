<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_monthly_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('salary_month', 7);
            $table->decimal('base_salary', 12, 2);
            $table->decimal('due_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2);
            $table->string('status', 20)->default('unpaid');
            $table->timestamps();

            $table->unique(['clinic_id', 'employee_id', 'salary_month'], 'emp_monthly_unique_idx');
            $table->index(['clinic_id', 'salary_month'], 'emp_monthly_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_monthly_salaries');
    }
};
