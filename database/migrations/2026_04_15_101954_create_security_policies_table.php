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
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete()->unique();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('password_min_length')->default(12);
            $table->boolean('require_mixed_case')->default(true);
            $table->boolean('require_numbers')->default(true);
            $table->boolean('require_symbols')->default(true);
            $table->unsignedSmallInteger('session_lifetime_minutes')->default(120);
            $table->unsignedSmallInteger('idle_timeout_minutes')->default(30);
            $table->boolean('force_two_factor')->default(false);
            $table->boolean('confirm_password_for_security_actions')->default(true);
            $table->unsignedSmallInteger('audit_retention_days')->default(365);
            $table->unsignedSmallInteger('sensitive_access_retention_days')->default(365);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_policies');
    }
};
