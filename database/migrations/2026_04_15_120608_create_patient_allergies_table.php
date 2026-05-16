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
        if (Schema::hasTable('patient_allergies')) {
            Schema::table('patient_allergies', function (Blueprint $table) {
                $table->string('allergy', 191)->change();
            });

            $indexNames = collect(Schema::getIndexes('patient_allergies'))
                ->pluck('name')
                ->all();

            Schema::table('patient_allergies', function (Blueprint $table) use ($indexNames) {
                if (! in_array('patient_allergies_unique', $indexNames, true)) {
                    $table->unique(['clinic_id', 'patient_id', 'allergy'], 'patient_allergies_unique');
                }

                if (! in_array('patient_allergies_clinic_id_patient_id_index', $indexNames, true)) {
                    $table->index(['clinic_id', 'patient_id']);
                }
            });

            return;
        }

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('allergy', 191);
            $table->timestamps();

            $table->unique(['clinic_id', 'patient_id', 'allergy'], 'patient_allergies_unique');
            $table->index(['clinic_id', 'patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_allergies');
    }
};
