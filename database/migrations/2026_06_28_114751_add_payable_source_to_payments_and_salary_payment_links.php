<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->change();
            $table->nullableMorphs('payable');
        });

        Schema::table('employee_salary_payments', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->after('paid_by')->constrained('payments')->nullOnDelete();
        });

        Schema::table('doctor_due_payments', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->after('paid_by')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doctor_due_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::table('employee_salary_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropMorphs('payable');
            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
