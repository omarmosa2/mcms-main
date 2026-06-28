<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the doctors system from scratch.
 *
 * Drops the legacy doctor_profiles and doctor_schedules tables (after
 * temporarily dropping the foreign keys that financial tables hold against
 * doctor_profiles) and recreates them with the new unified schema.
 *
 * Protected tables (never touched here): clinics, clinic_working_hours,
 * patients, appointments, and all financial tables themselves.
 */
return new class extends Migration
{
    /**
     * The financial tables that reference doctor_profiles. Their foreign keys
     * are dropped before recreating doctor_profiles and restored afterwards.
     */
    private const FINANCIAL_TABLES = [
        'doctor_deductions' => 'doctor_profile_id',
        'doctor_salary_payments' => 'doctor_profile_id',
        'doctor_monthly_dues' => 'doctor_id',
        'doctor_due_payments' => 'doctor_id',
        'doctor_appointment_entitlements' => 'doctor_profile_id',
    ];

    public function up(): void
    {
        $this->dropFinancialForeignKeys();

        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('doctor_profiles');

        Schema::create('doctor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('full_name');
            $table->string('gender', 20)->nullable();
            $table->string('specialty', 150);
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('username')->nullable()->unique();
            $table->date('employment_start_date')->nullable();
            $table->enum('compensation_type', ['percentage', 'fixed_weekly', 'fixed_monthly'])->default('percentage');
            $table->decimal('compensation_value', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'is_active']);
            $table->index(['clinic_id', 'compensation_type']);
            $table->index(['clinic_id', 'specialty']);
        });

        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doctor_profile_id')
                ->constrained('doctor_profiles')
                ->cascadeOnDelete();
            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete();
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['doctor_profile_id', 'day_of_week'], 'doctor_schedules_profile_day_unique');
            $table->index(['clinic_id', 'day_of_week']);
            $table->index(['doctor_profile_id', 'is_available']);
        });

        $this->restoreFinancialForeignKeys();
    }

    public function down(): void
    {
        $this->dropFinancialForeignKeys();

        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('doctor_profiles');

        // Restore the legacy schema so financial tables can reference it again.
        Schema::create('doctor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('license_number', 100)->nullable();
            $table->string('specialty', 150);
            $table->unsignedSmallInteger('consultation_duration_minutes')->default(30);
            $table->enum('status', ['active', 'on_leave', 'inactive'])->default('active');
            $table->json('work_schedule')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clinic_id', 'user_id']);
            $table->unique(['clinic_id', 'license_number']);
            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'specialty']);
        });

        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->tinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['clinic_id', 'doctor_id', 'day_of_week']);
            $table->index(['clinic_id', 'doctor_id']);
        });

        $this->restoreFinancialForeignKeys();
    }

    /**
     * Drop the foreign keys that financial tables hold against doctor_profiles
     * so that doctor_profiles can be safely dropped and recreated.
     */
    private function dropFinancialForeignKeys(): void
    {
        foreach (self::FINANCIAL_TABLES as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });
        }
    }

    /**
     * Restore the foreign keys from financial tables back to doctor_profiles.
     */
    private function restoreFinancialForeignKeys(): void
    {
        Schema::table('doctor_deductions', function (Blueprint $table): void {
            $table->foreign('doctor_profile_id', 'doctor_deductions_doctor_profile_id_foreign')
                ->references('id')
                ->on('doctor_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('doctor_salary_payments', function (Blueprint $table): void {
            $table->foreign('doctor_profile_id', 'doctor_salary_payments_doctor_profile_id_foreign')
                ->references('id')
                ->on('doctor_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('doctor_monthly_dues', function (Blueprint $table): void {
            $table->foreign('doctor_id', 'doctor_monthly_dues_doctor_id_foreign')
                ->references('id')
                ->on('doctor_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('doctor_due_payments', function (Blueprint $table): void {
            $table->foreign('doctor_id', 'doctor_due_payments_doctor_id_foreign')
                ->references('id')
                ->on('doctor_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('doctor_appointment_entitlements', function (Blueprint $table): void {
            $table->foreign('doctor_profile_id', 'doctor_appointment_entitlements_doctor_profile_id_foreign')
                ->references('id')
                ->on('doctor_profiles')
                ->cascadeOnDelete();
        });
    }
};
