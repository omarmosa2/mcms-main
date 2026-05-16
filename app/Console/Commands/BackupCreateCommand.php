<?php

namespace App\Console\Commands;

use App\Models\ComplianceRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('backup:create {--name= : Optional backup name prefix}')]
#[Description('Create a local on-prem backup artifact and manifest')]
class BackupCreateCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timestamp = now()->format('Ymd-His');
        $namePrefix = trim((string) ($this->option('name') ?? 'backup'));
        $backupDirectory = storage_path('app/backups');

        if (! File::isDirectory($backupDirectory)) {
            File::makeDirectory($backupDirectory, 0755, true);
        }

        $driver = (string) config('database.default');
        $databaseConfig = (array) config("database.connections.{$driver}");
        $artifactPath = '';

        try {
            if (($databaseConfig['driver'] ?? null) === 'sqlite') {
                $artifactPath = $this->createSqliteBackup($backupDirectory, $namePrefix, $timestamp, $databaseConfig);
            } else {
                $artifactPath = $this->createCliDumpBackup($backupDirectory, $namePrefix, $timestamp, $databaseConfig);
            }
        } catch (\Throwable $throwable) {
            $this->error('Backup creation failed: '.$throwable->getMessage());

            ComplianceRun::query()->create([
                'clinic_id' => null,
                'ran_by' => null,
                'run_type' => 'backup.create',
                'status' => 'failed',
                'summary' => [
                    'error' => $throwable->getMessage(),
                ],
                'ran_at' => now(),
            ]);

            return self::FAILURE;
        }

        $checksum = hash_file('sha256', $artifactPath);
        $manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.sprintf('manifest-%s.json', $timestamp);

        File::put($manifestPath, json_encode([
            'created_at' => now()->toISOString(),
            'driver' => $databaseConfig['driver'] ?? null,
            'artifact' => [
                'path' => $artifactPath,
                'sha256' => $checksum,
                'bytes' => File::size($artifactPath),
            ],
        ], JSON_PRETTY_PRINT));

        ComplianceRun::query()->create([
            'clinic_id' => null,
            'ran_by' => null,
            'run_type' => 'backup.create',
            'status' => 'completed',
            'summary' => [
                'artifact_path' => $artifactPath,
                'manifest_path' => $manifestPath,
                'sha256' => $checksum,
            ],
            'ran_at' => now(),
        ]);

        $this->info("Backup artifact: {$artifactPath}");
        $this->info("Backup manifest: {$manifestPath}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $databaseConfig
     */
    private function createSqliteBackup(string $backupDirectory, string $namePrefix, string $timestamp, array $databaseConfig): string
    {
        $databasePath = (string) ($databaseConfig['database'] ?? '');

        if ($databasePath === ':memory:') {
            $artifactPath = $backupDirectory.DIRECTORY_SEPARATOR.sprintf('%s-%s-memory.json', $namePrefix, $timestamp);

            File::put($artifactPath, json_encode([
                'driver' => 'sqlite',
                'database' => ':memory:',
                'created_at' => now()->toISOString(),
                'note' => 'In-memory SQLite connection cannot be copied as a file backup.',
            ], JSON_PRETTY_PRINT));

            return $artifactPath;
        }

        if ($databasePath === '' || ! File::exists($databasePath)) {
            throw new \RuntimeException('SQLite database file could not be located.');
        }

        $artifactPath = $backupDirectory.DIRECTORY_SEPARATOR.sprintf('%s-%s.sqlite', $namePrefix, $timestamp);

        File::copy($databasePath, $artifactPath);

        return $artifactPath;
    }

    /**
     * @param  array<string, mixed>  $databaseConfig
     */
    private function createCliDumpBackup(string $backupDirectory, string $namePrefix, string $timestamp, array $databaseConfig): string
    {
        $driver = (string) ($databaseConfig['driver'] ?? '');
        $artifactPath = $backupDirectory.DIRECTORY_SEPARATOR.sprintf('%s-%s.sql', $namePrefix, $timestamp);

        if ($driver === 'mysql') {
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
                escapeshellarg((string) ($databaseConfig['host'] ?? '127.0.0.1')),
                escapeshellarg((string) ($databaseConfig['port'] ?? '3306')),
                escapeshellarg((string) ($databaseConfig['username'] ?? 'root')),
                escapeshellarg((string) ($databaseConfig['password'] ?? '')),
                escapeshellarg((string) ($databaseConfig['database'] ?? '')),
                escapeshellarg($artifactPath),
            );

            $process = Process::run($command);

            if ($process->failed()) {
                throw new \RuntimeException(trim($process->errorOutput()) !== ''
                    ? trim($process->errorOutput())
                    : 'mysqldump returned a non-zero exit code.');
            }

            return $artifactPath;
        }

        if ($driver === 'pgsql') {
            $command = sprintf(
                'pg_dump --host=%s --port=%s --username=%s --dbname=%s --file=%s',
                escapeshellarg((string) ($databaseConfig['host'] ?? '127.0.0.1')),
                escapeshellarg((string) ($databaseConfig['port'] ?? '5432')),
                escapeshellarg((string) ($databaseConfig['username'] ?? 'postgres')),
                escapeshellarg((string) ($databaseConfig['database'] ?? 'postgres')),
                escapeshellarg($artifactPath),
            );

            $process = Process::env([
                'PGPASSWORD' => (string) ($databaseConfig['password'] ?? ''),
            ])->run($command);

            if ($process->failed()) {
                throw new \RuntimeException(trim($process->errorOutput()) !== ''
                    ? trim($process->errorOutput())
                    : 'pg_dump returned a non-zero exit code.');
            }

            return $artifactPath;
        }

        throw new \RuntimeException('Only sqlite, mysql, and pgsql backups are supported.');
    }
}
