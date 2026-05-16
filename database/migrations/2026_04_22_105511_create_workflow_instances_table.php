<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_instances')) {
            Schema::create('workflow_instances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
                $table->morphs('entity');
                $table->string('status')->default('pending');
                $table->integer('current_step');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['clinic_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
