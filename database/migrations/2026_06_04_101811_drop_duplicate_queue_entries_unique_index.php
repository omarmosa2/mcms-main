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
        if (! Schema::hasTable('queue_entries')) {
            return;
        }

        if (! $this->indexExists('queue_entries', 'queue_entries_clinic_date_number_unique')) {
            return;
        }

        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->dropUnique('queue_entries_clinic_date_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('queue_entries')) {
            return;
        }

        if ($this->indexExists('queue_entries', 'queue_entries_clinic_date_number_unique')) {
            return;
        }

        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->unique(['clinic_id', 'queue_date', 'queue_number'], 'queue_entries_clinic_date_number_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row): bool => ($row->name ?? null) === $index);
        }

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'select count(*) as aggregate from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
                [$table, $index],
            );

            return (int) ($result->aggregate ?? 0) > 0;
        }

        return collect(Schema::getIndexes($table))
            ->contains(fn (array $metadata): bool => ($metadata['name'] ?? null) === $index);
    }
};
