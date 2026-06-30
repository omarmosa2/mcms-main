<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drug_batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('drug_batches', 'purchase_price')) {
                $table->decimal('purchase_price', 12, 2)->nullable()->after('initial_quantity');
            }
            if (! Schema::hasColumn('drug_batches', 'selling_price')) {
                $table->decimal('selling_price', 12, 2)->nullable()->after('purchase_price');
            }
            if (! Schema::hasColumn('drug_batches', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('selling_price');
            }
            if (! Schema::hasColumn('drug_batches', 'notes')) {
                $table->text('notes')->nullable()->after('supplier_name');
            }
            if (! Schema::hasColumn('drug_batches', 'created_by')) {
                $table->unsignedInteger('created_by')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('drug_batches', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('drug_batches', function (Blueprint $table): void {
            $columnsToRemove = ['purchase_price', 'selling_price', 'supplier_name', 'notes', 'created_by'];
            $existingColumns = [];
            foreach ($columnsToRemove as $col) {
                if (Schema::hasColumn('drug_batches', $col)) {
                    $existingColumns[] = $col;
                }
            }
            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
