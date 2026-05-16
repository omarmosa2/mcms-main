<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_drug_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->integer('quantity');
            $table->integer('initial_quantity');
            $table->date('expiry_date');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'expiry_date']);
            $table->unique(['clinic_id', 'pharmacy_drug_id', 'batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_batches');
    }
};
