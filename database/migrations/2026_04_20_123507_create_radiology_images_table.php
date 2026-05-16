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
        Schema::create('radiology_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('radiology_order_id')->constrained('radiology_orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dicom_uid')->nullable();
            $table->string('file_disk', 32)->default('public');
            $table->string('file_path');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->dateTime('captured_at')->nullable();
            $table->string('pacs_study_id')->nullable();
            $table->string('pacs_instance_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'radiology_order_id']);
            $table->index(['clinic_id', 'dicom_uid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_images');
    }
};
