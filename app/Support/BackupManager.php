<?php

namespace App\Support;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class BackupManager
{
    public function __construct(private readonly SystemSettings $settings)
    {
    }

    public function run(string $type = 'manual', ?User $user = null): BackupLog
    {
        $log = BackupLog::query()->create([
            'user_id' => $user?->id,
            'backup_type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'database' => config('database.default'),
                'app_env' => config('app.env'),
            ],
        ]);

        $backupPath = $this->backupPath();
        $filename = 'backup_'.now()->format('Ymd_His').'.zip';
        $targetPath = $backupPath.DIRECTORY_SEPARATOR.$filename;
        $workPath = storage_path('app/backup-work/'.$log->id);

        try {
            File::ensureDirectoryExists($backupPath, 0750, true);
            File::ensureDirectoryExists($workPath, 0750, true);

            $this->writeDatabaseDump($workPath.DIRECTORY_SEPARATOR.'database.sql');
            $this->copyMemberPhotos($workPath);
            $this->copyConfigSnapshot($workPath);
            $this->writeManifest($workPath, $log, $filename);
            $this->zipDirectory($workPath, $targetPath);

            $log->update([
                'status' => 'completed',
                'filename' => $filename,
                'path' => $targetPath,
                'file_size' => File::size($targetPath),
                'checksum' => hash_file('sha256', $targetPath),
                'completed_at' => now(),
            ]);

            $this->cleanupRetention();

            return $log->fresh();
        } catch (Throwable $exception) {
            if (is_file($targetPath)) {
                File::delete($targetPath);
            }

            $log->update([
                'status' => 'failed',
                'filename' => $filename,
                'path' => null,
                'error_message' => str($exception->getMessage())->limit(1000)->toString(),
                'completed_at' => now(),
            ]);

            return $log->fresh();
        } finally {
            if (is_dir($workPath)) {
                File::deleteDirectory($workPath);
            }
        }
    }

    public function cleanupRetention(): int
    {
        $retention = max(1, (int) $this->settings->get('backup_retention_count', config('gym.backup.retention_count', 30)));
        $expired = BackupLog::query()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->skip($retention)
            ->take(PHP_INT_MAX)
            ->get();

        $deleted = 0;

        foreach ($expired as $log) {
            if ($log->path && is_file($log->path)) {
                File::delete($log->path);
                $deleted++;
            }

            $log->update([
                'status' => 'deleted',
                'error_message' => 'Removed by backup retention cleanup.',
            ]);
        }

        return $deleted;
    }

    public function backupPath(): string
    {
        return rtrim((string) $this->settings->get('backup_path', config('gym.backup.path')), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{status: string, error_message: string|null}
     */
    public function restore(BackupLog $backup): array
    {
        if (! $backup->isDownloadable()) {
            return [
                'status' => 'failed',
                'error_message' => 'Backup file is not available for restore.',
            ];
        }

        $workPath = storage_path('app/backup-restore/'.$backup->id.'-'.now()->format('YmdHis'));

        try {
            File::ensureDirectoryExists($workPath, 0750, true);
            $this->extractZip($backup->path, $workPath);

            $databaseDump = $workPath.DIRECTORY_SEPARATOR.'database.sql';

            if (! is_file($databaseDump)) {
                throw new RuntimeException('Backup file does not contain database.sql.');
            }

            $this->restoreDatabaseDump($databaseDump);
            $this->restoreMemberPhotos($workPath);
            $this->preserveRestoredBackupRecord($backup);

            return [
                'status' => 'completed',
                'error_message' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error_message' => str($exception->getMessage())->limit(1000)->toString(),
            ];
        } finally {
            if (is_dir($workPath)) {
                File::deleteDirectory($workPath);
            }
        }
    }

    private function writeDatabaseDump(string $path): void
    {
        $connection = config('database.default');

        if ($connection !== 'pgsql') {
            File::put($path, "-- Database dump skipped: {$connection} connection is used in this environment.\n");

            return;
        }

        $config = config('database.connections.pgsql');
        $binary = (string) $this->settings->get('backup.pg_dump_binary', config('gym.backup.pg_dump_binary', 'pg_dump'));
        $process = new Process(array_values(array_filter([
            $binary,
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--username='.$config['username'],
            '--dbname='.$config['database'],
            '--no-owner',
            '--no-privileges',
            '--clean',
            '--if-exists',
            '--file='.$path,
        ])));

        if (! empty($config['password'])) {
            $process->setEnv(['PGPASSWORD' => $config['password']]);
        }

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path)) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'PostgreSQL dump failed.');
        }
    }

    private function copyMemberPhotos(string $workPath): void
    {
        $source = storage_path('app/public/'.trim((string) config('gym.members.photo_path', 'members/photos'), '/'));
        $target = $workPath.DIRECTORY_SEPARATOR.'member_photos';

        if (is_dir($source)) {
            File::copyDirectory($source, $target);

            return;
        }

        File::ensureDirectoryExists($target, 0750, true);
        File::put($target.DIRECTORY_SEPARATOR.'.empty', 'No member photos found at backup time.');
    }

    private function copyConfigSnapshot(string $workPath): void
    {
        $target = $workPath.DIRECTORY_SEPARATOR.'config';
        File::ensureDirectoryExists($target, 0750, true);

        foreach (['config/app.php', 'config/database.php', 'config/gym.php'] as $file) {
            $source = base_path($file);

            if (is_file($source)) {
                File::copy($source, $target.DIRECTORY_SEPARATOR.basename($file));
            }
        }

        if (is_file(base_path('.env.example'))) {
            File::copy(base_path('.env.example'), $target.DIRECTORY_SEPARATOR.'.env.example');
        }
    }

    private function writeManifest(string $workPath, BackupLog $log, string $filename): void
    {
        File::put($workPath.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'filename' => $filename,
            'backup_type' => $log->backup_type,
            'started_at' => $log->started_at?->toIso8601String(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'database' => config('database.default'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function zipDirectory(string $source, string $targetPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create backup zip file.');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getRealPath();
            $relativePath = ltrim(str_replace($source, '', (string) $path), DIRECTORY_SEPARATOR);
            $zip->addFile((string) $path, $relativePath);
        }

        $zip->close();

        if (! is_file($targetPath)) {
            throw new RuntimeException('Backup zip file was not created.');
        }
    }

    private function extractZip(string $zipPath, string $targetPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open backup zip file.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name)) {
                $zip->close();

                throw new InvalidArgumentException('Backup zip contains an unsafe file path.');
            }
        }

        if (! $zip->extractTo($targetPath)) {
            $zip->close();

            throw new RuntimeException('Unable to extract backup zip file.');
        }

        $zip->close();
    }

    private function restoreDatabaseDump(string $path): void
    {
        $connection = config('database.default');

        if ($connection !== 'pgsql') {
            return;
        }

        $config = config('database.connections.pgsql');
        $binary = (string) $this->settings->get('backup.psql_binary', config('gym.backup.psql_binary', 'psql'));
        $process = new Process(array_values(array_filter([
            $binary,
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--username='.$config['username'],
            '--dbname='.$config['database'],
            '--set=ON_ERROR_STOP=1',
            '--file='.$path,
        ])));

        if (! empty($config['password'])) {
            $process->setEnv(['PGPASSWORD' => $config['password']]);
        }

        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'PostgreSQL restore failed.');
        }
    }

    private function restoreMemberPhotos(string $workPath): void
    {
        $source = $workPath.DIRECTORY_SEPARATOR.'member_photos';
        $target = storage_path('app/public/'.trim((string) config('gym.members.photo_path', 'members/photos'), '/'));

        if (! is_dir($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($target), 0750, true);

        if (is_dir($target)) {
            File::deleteDirectory($target);
        }

        File::copyDirectory($source, $target);
    }

    private function preserveRestoredBackupRecord(BackupLog $backup): void
    {
        BackupLog::query()->updateOrCreate([
            'id' => $backup->id,
        ], [
            'user_id' => $backup->user_id,
            'backup_type' => $backup->backup_type,
            'status' => 'completed',
            'filename' => $backup->filename,
            'path' => $backup->path,
            'file_size' => $backup->file_size,
            'checksum' => $backup->checksum,
            'started_at' => $backup->started_at,
            'completed_at' => $backup->completed_at,
            'error_message' => null,
            'metadata' => $backup->metadata,
        ]);
    }
}
