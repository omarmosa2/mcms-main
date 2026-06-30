<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('prescription_items', 'status')) {
                $table->string('status')->default('pending')->after('instructions');
            }
            if (! Schema::hasColumn('prescription_items', 'quantity_dispensed')) {
                $table->unsignedInteger('quantity_dispensed')->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('prescription_items', 'substitution_allowed')) {
                $table->boolean('substitution_allowed')->default(true)->after('quantity_dispensed');
            }
            if (! Schema::hasColumn('prescription_items', 'dispensed_batch_id')) {
                $table->foreignId('dispensed_batch_id')->nullable()->after('substitution_allowed');
            }
            if (! Schema::hasColumn('prescription_items', 'notes')) {
                $table->text('notes')->nullable()->after('dispensed_batch_id');
            }
            if (! Schema::hasColumn('prescription_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('prescription_items', 'status')) {
            Schema::table('prescription_items', function (Blueprint $table): void {
                $table->index(['clinic_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            if (Schema::hasColumn('prescription_items', 'status')) {
                $table->dropIndex(['clinic_id', 'status']);
            }

            $columnsToRemove = ['status', 'quantity_dispensed', 'substitution_allowed', 'dispensed_batch_id', 'notes'];
            $existingColumns = [];
            foreach ($columnsToRemove as $col) {
                if (Schema::hasColumn('prescription_items', $col)) {
                    $existingColumns[] = $col;
                }
            }
            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
