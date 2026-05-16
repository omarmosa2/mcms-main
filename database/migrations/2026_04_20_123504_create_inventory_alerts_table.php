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
        Schema::create('inventory_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('pharmacy_drug_id')->nullable()->constrained('pharmacy_drugs')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['low_stock', 'near_expiry', 'expired']);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->string('message');
            $table->json('metadata')->nullable();
            $table->dateTime('detected_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status', 'type', 'detected_at']);
            $table->index(['clinic_id', 'pharmacy_drug_id', 'type', 'status'], 'inventory_alert_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_alerts');
    }
};
