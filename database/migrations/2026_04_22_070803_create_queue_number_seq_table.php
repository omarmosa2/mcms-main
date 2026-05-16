<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queue_number_seq', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'queue_date']);
        });

        $this->populateExistingData();
    }

    private function populateExistingData(): void
    {
        $existingSequences = DB::table('queue_entries')
            ->select('clinic_id', 'queue_date', DB::raw('MAX(queue_number) as max_number'))
            ->groupBy('clinic_id', 'queue_date')
            ->get();

        foreach ($existingSequences as $sequence) {
            DB::table('queue_number_seq')->insert([
                'clinic_id' => $sequence->clinic_id,
                'queue_date' => $sequence->queue_date,
                'current_value' => $sequence->max_number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_number_seq');
    }
};
