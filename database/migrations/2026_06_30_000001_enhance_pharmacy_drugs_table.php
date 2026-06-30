<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_drugs', function (Blueprint $table): void {
            if (! Schema::hasColumn('pharmacy_drugs', 'code')) {
                $table->string('code')->nullable()->after('clinic_id');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'barcode')) {
                $table->string('barcode')->nullable()->after('code');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'category')) {
                $table->string('category')->nullable()->after('generic_name');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'form')) {
                $table->string('form')->nullable()->after('category');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'unit')) {
                $table->string('unit')->nullable()->after('form');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'manufacturer')) {
                $table->string('manufacturer')->nullable()->after('strength');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'description')) {
                $table->text('description')->nullable()->after('manufacturer');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'created_by')) {
                $table->unsignedInteger('created_by')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'updated_by')) {
                $table->unsignedInteger('updated_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('pharmacy_drugs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('pharmacy_drugs', 'code')) {
            Schema::table('pharmacy_drugs', function (Blueprint $table): void {
                $table->index(['clinic_id', 'code']);
                $table->index(['clinic_id', 'barcode']);
                $table->index(['clinic_id', 'category']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('pharmacy_drugs', function (Blueprint $table): void {
            if (Schema::hasColumn('pharmacy_drugs', 'code')) {
                $table->dropIndex(['clinic_id', 'code']);
            }
            if (Schema::hasColumn('pharmacy_drugs', 'barcode')) {
                $table->dropIndex(['clinic_id', 'barcode']);
            }
            if (Schema::hasColumn('pharmacy_drugs', 'category')) {
                $table->dropIndex(['clinic_id', 'category']);
            }

            $columnsToRemove = ['code', 'barcode', 'category', 'form', 'unit', 'manufacturer', 'description', 'created_by', 'updated_by'];
            $existingColumns = [];
            foreach ($columnsToRemove as $col) {
                if (Schema::hasColumn('pharmacy_drugs', $col)) {
                    $existingColumns[] = $col;
                }
            }
            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
