<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('group')->index();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'group', 'key']);
            $table->index(['clinic_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};
