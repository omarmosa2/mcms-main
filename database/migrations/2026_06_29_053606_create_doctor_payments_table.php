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
        Schema::create('doctor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctor_profiles')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_type', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('dedupe_key')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['doctor_id', 'payment_type', 'dedupe_key'], 'doctor_payments_period_unique');
            $table->index(['clinic_id', 'period_start', 'period_end'], 'doctor_simple_payments_clinic_period_idx');
            $table->index(['clinic_id', 'doctor_id'], 'doctor_simple_payments_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_payments');
    }
};
