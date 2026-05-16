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
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete()->unique();
            $table->string('company_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('theme_tokens')->nullable();
            $table->string('locale_default', 5)->default('en');
            $table->string('domain')->nullable();
            $table->timestamps();

            $table->index(['locale_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
