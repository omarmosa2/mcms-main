<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('marital_status', 20)->nullable()->after('national_id');
            $table->string('specialty', 150)->nullable()->after('employee_type');
            $table->text('job_description')->nullable()->after('specialty');
            $table->renameColumn('certificate_type', 'certificate_name');
            $table->string('education_specialty', 150)->nullable()->after('certificate_name');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('education_specialty');
            $table->string('issuing_institution', 255)->nullable()->after('graduation_year');
            $table->decimal('additional_allowance', 12, 2)->nullable()->after('base_salary');
            $table->foreignId('user_id')->nullable()->after('clinic_id')->constrained('users')->nullOnDelete();

            $table->index(['clinic_id', 'marital_status'], 'employees_clinic_marital_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex('employees_clinic_marital_idx');
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'marital_status',
                'specialty',
                'job_description',
                'education_specialty',
                'graduation_year',
                'issuing_institution',
                'additional_allowance',
                'user_id',
            ]);
            $table->renameColumn('certificate_name', 'certificate_type');
        });
    }
};
