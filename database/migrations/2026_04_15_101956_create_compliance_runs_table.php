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
        Schema::create('compliance_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('ran_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_type');
            $table->string('status')->default('completed');
            $table->json('summary')->nullable();
            $table->timestamp('ran_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'run_type', 'ran_at'], 'compliance_runs_clinic_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_runs');
    }
};
