<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseBackup extends Command
{
    protected $signature = 'backup:run {--restore= : Restore from a specific backup file}';

    protected $description = 'Create or restore database backup';

    public function handle(): int
    {
        $restoreFile = $this->option('restore');

        if ($restoreFile) {
            return $this->restoreBackup($restoreFile);
        }

        return $this->createBackup();
    }

    private function createBackup(): int
    {
        $this->info('جاري إنشاء نسخة احتياطية...');

        $dbPath = database_path('database.sqlite');

        if (! File::exists($dbPath)) {
            $this->error('ملف قاعدة البيانات غير موجود.');

            return Command::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'-'.Str::random(8).'.sqlite.gz';
        $backupPath = $backupDir.'/'.$filename;

        $content = File::get($dbPath);
        $compressed = gzencode($content, 9);

        if ($compressed === false) {
            $this->error('فشل ضغط النسخة الاحتياطية.');

            return Command::FAILURE;
        }

        File::put($backupPath, $compressed);

        $this->info("تم إنشاء النسخة الاحتياطية: {$filename}");

        $this->cleanOldBackups($backupDir);

        $size = round(File::size($backupPath) / 1024 / 1024, 2);
        $this->info("حجم النسخة: {$size} MB");

        return Command::SUCCESS;
    }

    private function restoreBackup(string $filename): int
    {
        $backupDir = storage_path('app/backups');
        $backupPath = $backupDir.'/'.$filename;

        if (! File::exists($backupPath)) {
            $this->error("ملف النسخة الاحتياطية غير موجود: {$filename}");

            return Command::FAILURE;
        }

        $dbPath = database_path('database.sqlite');

        $compressed = File::get($backupPath);
        $content = gzdecode($compressed);

        if ($content === false) {
            $this->error('فشل فك ضغط النسخة الاحتياطية.');

            return Command::FAILURE;
        }

        File::put($dbPath, $content);

        $this->info("تم استعادة النسخة الاحتياطية: {$filename}");

        return Command::SUCCESS;
    }

    private function cleanOldBackups(string $backupDir): void
    {
        $files = File::files($backupDir);
        $retentionDays = 30;

        foreach ($files as $file) {
            if (now()->diffInDays(now()->createFromTimestamp($file->getMTime())) > $retentionDays) {
                File::delete($file->getPathname());
                $this->info("تم حذف نسخة قديمة: {$file->getFilename()}");
            }
        }
    }
}
