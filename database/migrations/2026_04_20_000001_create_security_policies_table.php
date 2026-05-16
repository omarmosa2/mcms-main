<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (\Schema::hasTable('security_policies')) {
            return;
        }
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->boolean('force_two_factor')->default(false);
            $table->unsignedTinyInteger('session_lifetime_minutes')->nullable();
            $table->unsignedTinyInteger('idle_timeout_minutes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_policies');
    }
};
