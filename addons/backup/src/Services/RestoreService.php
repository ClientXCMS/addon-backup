<?php

namespace App\Addons\Backup\Services;

use App\Addons\Backup\Exceptions\BackupException;
use App\Addons\Backup\Support\ZipHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RestoreService
{
    public function __construct(
        protected BackupService $backupService
    ) {}
    public function restoreDatabaseFromBackup(string $identifier): void
    {
        $this->withExtractedBackup($identifier, function (string $path) {
            $sqlFile = $path . '/database.sql';
            if (! File::exists($sqlFile)) {
                throw BackupException::missingArtifact('database.sql');
            }
            $this->importDatabase($sqlFile);
        });
    }

    public function restoreStorageFromBackup(string $identifier): void
    {
        $this->withExtractedBackup($identifier, function (string $path) {
            $archive = $path . '/storage.zip';
            if (! File::exists($archive)) {
                throw BackupException::missingArtifact('storage.zip');
            }

            $temp = $path . '/storage_extract';
            ZipHelper::extractZip($archive, $temp);

            $target = storage_path('app');
            File::deleteDirectory($target);
            File::ensureDirectoryExists($target);
            File::copyDirectory($temp, $target);
        });
    }

    public function restoreAllFromBackup(string $identifier): void
    {
        $this->withExtractedBackup($identifier, function (string $path) {
            if (File::exists($path . '/database.sql')) {
                $this->importDatabase($path . '/database.sql');
            }
            if (File::exists($path . '/storage.zip')) {
                $temp = $path . '/storage_extract';
                ZipHelper::extractZip($path . '/storage.zip', $temp);
                $target = storage_path('app');
                File::deleteDirectory($target);
                File::ensureDirectoryExists($target);
                File::copyDirectory($temp, $target);
            }
        });
    }

    protected function withExtractedBackup(string $identifier, callable $callback): void
    {
        $workingDir = storage_path('framework/backup/restore-' . Str::uuid());
        $zipPath = $workingDir . '.zip';
        File::ensureDirectoryExists($workingDir);
        File::ensureDirectoryExists(dirname($zipPath));

        try {
            $destination = $this->backupService->findAndSetProviderForBackup($identifier);
            if (! $destination) {
                throw new BackupException("Backup destination not found for identifier: {$identifier}");
            }
            $destination->downloadBackup($identifier, $zipPath);
            ZipHelper::extractZip($zipPath, $workingDir);
            $callback($workingDir);
        } finally {
            File::delete($zipPath);
            File::deleteDirectory($workingDir);
        }
    }

    protected function importDatabase(string $filePath): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.$connectionName");
        $driver = $connection['driver'] ?? $connectionName;

        match ($driver) {
            'mysql', 'mariadb' => $this->importMysql($connection, $filePath),
            'pgsql' => $this->importPostgres($connection, $filePath),
            'sqlite' => $this->importSqlite($connection, $filePath),
            default => throw BackupException::unsupportedDriver($driver),
        };
    }

    protected function importMysql(array $connection, string $filePath): void
    {
        $command = [
            'mysql',
            '--host=' . (string) ($connection['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($connection['port'] ?? 3306),
            '--user=' . (string) $connection['username'],
            (string) $connection['database'],
        ];

        if (! empty($connection['unix_socket'])) {
            $command[] = '--socket=' . (string) $connection['unix_socket'];
        }

        $handle = fopen($filePath, 'r');
        $process = new Process($command, null, empty($connection['password']) ? [] : ['MYSQL_PWD' => $connection['password']]);
        $process->setInput($handle);
        $this->runProcess($process);
        fclose($handle);
    }

    protected function importPostgres(array $connection, string $filePath): void
    {
        $command = [
            'psql',
            '--host=' . (string) ($connection['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($connection['port'] ?? 5432),
            '--username=' . (string) $connection['username'],
            '--dbname=' . (string) $connection['database'],
        ];

        $handle = fopen($filePath, 'r');
        $process = new Process($command, null, empty($connection['password']) ? [] : ['PGPASSWORD' => $connection['password']]);
        $process->setInput($handle);
        $this->runProcess($process);
        fclose($handle);
    }

    protected function importSqlite(array $connection, string $filePath): void
    {
        $database = $connection['database'];
        if (! str_starts_with($database, '/')) {
            $database = database_path($database);
        }

        File::copy($filePath, $database);
    }

    protected function runProcess(Process $process): void
    {
        try {
            $process->setTimeout(null);
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new BackupException($exception->getMessage());
        }
    }
}
