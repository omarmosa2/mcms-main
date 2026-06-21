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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->nullOnDelete();
            $table->string('full_name');
            $table->string('gender', 20);
            $table->date('birth_date')->nullable();
            $table->string('phone', 50);
            $table->text('address')->nullable();
            $table->string('national_id')->nullable();
            $table->date('hire_date');
            $table->string('status', 20)->default('active');
            $table->string('job_title');
            $table->string('employee_type', 50);
            $table->string('education_level', 50)->nullable();
            $table->string('certificate_type')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->text('salary_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status'], 'employees_clinic_status_idx');
            $table->index(['clinic_id', 'employee_type'], 'employees_clinic_type_idx');
            $table->index(['clinic_id', 'department_id'], 'employees_clinic_department_idx');
            $table->index(['clinic_id', 'hire_date'], 'employees_clinic_hire_date_idx');
            $table->unique(['clinic_id', 'national_id'], 'employees_clinic_national_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
