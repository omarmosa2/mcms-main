<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('pharmacy_drug_id')->constrained('pharmacy_drugs')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('drug_batches')->nullOnDelete();
            $table->string('movement_type');
            $table->integer('quantity');
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['clinic_id', 'pharmacy_drug_id', 'created_at'], 'psm_drug_created_idx');
            $table->index(['clinic_id', 'movement_type'], 'psm_clinic_type_idx');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stock_movements');
    }
};
