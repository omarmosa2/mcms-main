<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_positive_totals CHECK (total_amount >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_paid_not_negative CHECK (paid_amount >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_balance_not_negative CHECK (balance_amount >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_paid_not_exceed_total CHECK (paid_amount <= total_amount)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_balance_not_exceed_total CHECK (balance_amount <= total_amount)');

            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payment_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_refund_not_exceed CHECK (refund_amount <= amount)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_refund_not_negative CHECK (refund_amount >= 0)');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->change();
            $table->unsignedBigInteger('patient_id')->change();
            $table->unsignedBigInteger('visit_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->change();
            $table->unsignedBigInteger('invoice_id')->change();
            $table->unsignedBigInteger('received_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_positive_totals');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_paid_not_negative');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_balance_not_negative');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_paid_not_exceed_total');
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_balance_not_exceed_total');

            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_payment_amount_positive');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_refund_not_exceed');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT chk_refund_not_negative');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('clinic_id')->change();
            $table->bigInteger('patient_id')->change();
            $table->bigInteger('visit_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('clinic_id')->change();
            $table->bigInteger('invoice_id')->change();
            $table->bigInteger('received_by')->change();
        });
    }
};
