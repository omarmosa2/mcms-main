<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->addInvoiceConstraints();
        $this->addInvoiceItemConstraints();
        $this->addPaymentPlanConstraints();
        $this->addInstallmentConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->dropInvoiceConstraints();
        $this->dropInvoiceItemConstraints();
        $this->dropPaymentPlanConstraints();
        $this->dropInstallmentConstraints();
    }

    private function addInvoiceConstraints(): void
    {
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_subtotal_non_negative CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_discount_non_negative CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_tax_non_negative CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_discount_within_subtotal CHECK (discount_amount <= subtotal_amount)');
    }

    private function dropInvoiceConstraints(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_invoice_subtotal_non_negative');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_invoice_discount_non_negative');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_invoice_tax_non_negative');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT chk_invoice_discount_within_subtotal');
    }

    private function addInvoiceItemConstraints(): void
    {
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_discount_non_negative CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_tax_non_negative CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_line_total_non_negative CHECK (line_total >= 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_item_discount_within_line CHECK (discount_amount <= (quantity * unit_price))');
    }

    private function dropInvoiceItemConstraints(): void
    {
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_quantity_positive');
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_unit_price_non_negative');
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_discount_non_negative');
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_tax_non_negative');
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_line_total_non_negative');
        DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT chk_item_discount_within_line');
    }

    private function addPaymentPlanConstraints(): void
    {
        DB::statement('ALTER TABLE payment_plans ADD CONSTRAINT chk_plan_installment_count_positive CHECK (installment_count > 0)');
        DB::statement('ALTER TABLE payment_plans ADD CONSTRAINT chk_plan_min_amount_non_negative CHECK (min_amount >= 0)');
    }

    private function dropPaymentPlanConstraints(): void
    {
        DB::statement('ALTER TABLE payment_plans DROP CONSTRAINT chk_plan_installment_count_positive');
        DB::statement('ALTER TABLE payment_plans DROP CONSTRAINT chk_plan_min_amount_non_negative');
    }

    private function addInstallmentConstraints(): void
    {
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installment_number_positive CHECK (installment_number > 0)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installment_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installment_paid_non_negative CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT chk_installment_paid_within_amount CHECK (paid_amount <= amount)');
    }

    private function dropInstallmentConstraints(): void
    {
        DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_number_positive');
        DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_amount_positive');
        DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_paid_non_negative');
        DB::statement('ALTER TABLE installments DROP CONSTRAINT chk_installment_paid_within_amount');
    }
};
