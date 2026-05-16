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
        Schema::create('pharmacy_drugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('trade_name');
            $table->string('generic_name');
            $table->string('dosage_form')->nullable();
            $table->string('strength')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('min_stock_level')->default(0);
            $table->unsignedInteger('current_stock')->default(0);
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['clinic_id', 'trade_name']);
            $table->index(['clinic_id', 'current_stock', 'min_stock_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacy_drugs');
    }
};
