<?php

namespace App\Jobs;

use App\Events\ImportCompleted;
use App\Imports\PatientImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportPatientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public int $userId,
        public int $clinicId,
        public string $filePath,
        public string $disk = 'local',
    ) {}

    public function handle(): void
    {
        $cacheKey = "import:patients:{$this->userId}";

        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Processing import file...',
        ], now()->addHours(2));

        $import = new PatientImport($this->clinicId, $this->userId);

        try {
            Excel::import($import, $this->filePath, $this->disk);

            $result = [
                'status' => 'completed',
                'progress' => 100,
                'imported' => $import->importedCount,
                'failed' => $import->failedCount,
                'errors' => $import->errors,
                'message' => sprintf('Import completed: %d imported, %d failed.', $import->importedCount, $import->failedCount),
            ];
        } catch (\Throwable $e) {
            $result = [
                'status' => 'failed',
                'progress' => 0,
                'imported' => $import->importedCount,
                'failed' => $import->failedCount,
                'errors' => $import->errors,
                'message' => 'Import failed: '.$e->getMessage(),
            ];
        }

        Cache::put($cacheKey, $result, now()->addHours(2));

        ImportCompleted::dispatch($this->userId, $result);

        if (Storage::disk($this->disk)->exists($this->filePath)) {
            Storage::disk($this->disk)->delete($this->filePath);
        }
    }
}
