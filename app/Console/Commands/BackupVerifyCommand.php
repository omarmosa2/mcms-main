<?php

namespace App\Console\Commands;

use App\Models\ComplianceRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('backup:verify')]
#[Description('Verify integrity of the latest backup artifact and manifest')]
class BackupVerifyCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDirectory = storage_path('app/backups');

        if (! File::isDirectory($backupDirectory)) {
            $this->error('Backup directory does not exist.');

            return self::FAILURE;
        }

        $manifestPaths = collect(File::files($backupDirectory))
            ->filter(fn (\SplFileInfo $file): bool => str_starts_with($file->getFilename(), 'manifest-'))
            ->sortByDesc(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values();

        if ($manifestPaths->isEmpty()) {
            $this->error('No backup manifest files were found.');

            return self::FAILURE;
        }

        $latestManifestPath = $manifestPaths->first()->getPathname();
        $manifest = json_decode((string) File::get($latestManifestPath), true);

        if (! is_array($manifest)) {
            $this->error('Latest backup manifest is invalid JSON.');

            return self::FAILURE;
        }

        $artifactPath = (string) ($manifest['artifact']['path'] ?? '');
        $expectedHash = (string) ($manifest['artifact']['sha256'] ?? '');

        if ($artifactPath === '' || ! File::exists($artifactPath)) {
            $this->error('Backup artifact referenced by the manifest was not found.');

            return self::FAILURE;
        }

        $actualHash = hash_file('sha256', $artifactPath);

        if ($expectedHash === '' || $actualHash !== $expectedHash) {
            $this->error('Backup artifact checksum validation failed.');

            ComplianceRun::query()->create([
                'clinic_id' => null,
                'ran_by' => null,
                'run_type' => 'backup.verify',
                'status' => 'failed',
                'summary' => [
                    'manifest' => $latestManifestPath,
                    'artifact' => $artifactPath,
                    'expected_sha256' => $expectedHash,
                    'actual_sha256' => $actualHash,
                ],
                'ran_at' => now(),
            ]);

            return self::FAILURE;
        }

        ComplianceRun::query()->create([
            'clinic_id' => null,
            'ran_by' => null,
            'run_type' => 'backup.verify',
            'status' => 'completed',
            'summary' => [
                'manifest' => $latestManifestPath,
                'artifact' => $artifactPath,
                'sha256' => $actualHash,
            ],
            'ran_at' => now(),
        ]);

        $this->info('Backup verification completed successfully.');
        $this->line("Manifest: {$latestManifestPath}");
        $this->line("Artifact: {$artifactPath}");

        return self::SUCCESS;
    }
}
