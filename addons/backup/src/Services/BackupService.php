<?php

namespace App\Addons\Backup\Services;

use App\Addons\Backup\Contracts\BackupDestination;
use App\Addons\Backup\Destinations\FtpBackupDestination;
use App\Addons\Backup\Destinations\GoogleDriveBackupDestination;
use App\Addons\Backup\Destinations\LocalBackupDestination;
use App\Addons\Backup\DTO\BackupResult;
use App\Addons\Backup\Exceptions\BackupException;
use App\Addons\Backup\Models\BackupProvider;
use App\Addons\Backup\Support\ZipHelper;
use App\Addons\Backup\Backup\BackupDumper;
use App\Models\Admin\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BackupService
{
    private ?BackupDestination $destination = null;
    /**
     * Configure the service for a specific provider.
     */
    public function forProvider(BackupProvider $provider): self
    {
        $config = $provider->configuration ?? [];
        $config['driver'] = $provider->driver;
        if (array_key_exists('port', $config)) {
            // Ensure port is an integer
            $config['port'] = (int) $config['port'];
        }
        config(['filesystems.disks.backups' => $config]);

        $this->destination = $this->createDestination();

        return $this;
    }

    /**
     * Create the appropriate destination based on current disk config.
     */
    protected function createDestination(): BackupDestination
    {
        $config = config('filesystems.disks.backups', []);
        $driver = $config['driver'] ?? 'local';
        $disk = 'backups';
        $basePath = $config['base_path'] ?? 'backups';
        if ($basePath === '') {
            $basePath = '.';
        }

        if (($driver === 'sftp' || $driver === 'ftp')) {
            if ($driver === 'sftp' && ! class_exists('League\Flysystem\PhpseclibV3\SftpConnectionProvider')) {
                throw new BackupException("The SFTP driver requires the 'league/flysystem-sftp-v3' package. Run: composer require league/flysystem-sftp-v3");
            }

            if ($driver === 'ftp' && ! class_exists('League\Flysystem\Ftp\FtpAdapter')) {
                throw new BackupException("The FTP driver requires the 'league/flysystem-ftp' package. Run: composer require league/flysystem-ftp");
            }
        }

        if ($driver === 'google' && ! class_exists('Masbug\Flysystem\GoogleDriveAdapter')) {
            throw new BackupException("The Google Drive driver requires 'masbug/flysystem-google-drive-ext' and 'google/apiclient'. Run: composer require masbug/flysystem-google-drive-ext google/apiclient");
        }

        return match ($driver) {
            'ftp', 'sftp' => new FtpBackupDestination($disk, $basePath),
            'google' => new GoogleDriveBackupDestination($disk, $basePath),
            default => new LocalBackupDestination($disk, $basePath),
        };
    }

    public function getDestination(): ?BackupDestination
    {
        return $this->destination;
    }

    /**
     * Get a backup descriptor by its identifier.
     */
    public function getBackup(string $identifier): ?\App\Addons\Backup\DTO\BackupDescriptor
    {
        if ($this->destination) {
            return $this->destination->getBackup($identifier);
        }

        return $this->findAndSetProviderForBackup($identifier)?->getBackup($identifier);
    }

    /**
     * Find the provider for a given backup identifier and set it.
     */
    public function findAndSetProviderForBackup(string $identifier): ?BackupDestination
    {
        // First try via logs
        $log = \App\Addons\Backup\Models\BackupLog::where('identifier', $identifier)->first();
        if ($log && $log->provider) {
            $this->forProvider($log->provider);
            return $this->destination;
        }

        // Fallback: search all active providers
        $providers = BackupProvider::where('enabled', true)->get();
        foreach ($providers as $provider) {
            $this->forProvider($provider);
            if ($this->destination->getBackup($identifier)) {
                return $this->destination;
            }
        }

        return null;
    }

    public function runBackup(bool $includeDatabase = true, bool $includeStorage = true, ?int $retentionDays = null, ?bool $manual = false): BackupResult
    {
        if (! $includeDatabase && ! $includeStorage) {
            throw new BackupException('At least one backup section (database or storage) must be selected.');
        }

        $identifier = (string) Str::uuid();
        $workingDirectory = storage_path('framework/backup/' . $identifier);
        File::ensureDirectoryExists($workingDirectory);

        if ($includeDatabase) {
            $this->dumpDatabase($workingDirectory . '/database.sql');
        }

        if ($includeStorage) {
            $this->archiveStorage($workingDirectory . '/storage.zip');
        }

        $type = match (true) {
            $includeDatabase && $includeStorage => 'full',
            $includeDatabase => 'database',
            default => 'files',
        };

        $metadata = [
            'uuid' => $identifier,
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'app_key' => config('app.key'),
            'app_url' => config('app.url'),
            'includes_database' => $includeDatabase,
            'includes_storage' => $includeStorage,
            'database_connection' => config('database.default'),
            'filename' => 'backup-' . $identifier . '.zip',
        ];

        File::put($workingDirectory . '/backup.json', json_encode($metadata, JSON_PRETTY_PRINT));

        try {
            $this->destination->store($workingDirectory);
        } finally {
            File::deleteDirectory($workingDirectory);
        }

        $this->applyRetention($retentionDays);
        if (!$manual) {
            Setting::updateSettings(['backup_last_run' => now()->toDateTimeString()]);
        }

        return new BackupResult($identifier, $type, $includeDatabase, $includeStorage, Carbon::parse($metadata['created_at']), $metadata);
    }

    protected function applyRetention(?int $retentionDays = null): void
    {
        $days = $retentionDays ?? (int) setting('backup_retention_days', 7);
        if ($days <= 0) {
            return;
        }

        $limit = now()->subDays($days);
        foreach ($this->destination->listBackups() as $backup) {
            if ($backup->createdAt->lt($limit)) {
                $this->destination->deleteBackup($backup->identifier);
            }
        }
    }

    protected function dumpDatabase(string $targetPath): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.$connectionName");
        $driver = $connection['driver'] ?? $connectionName;

        match ($driver) {
            'mysql', 'mariadb' => $this->dumpMysql($connection, $targetPath),
            'pgsql' => $this->dumpPostgres($connection, $targetPath),
            'sqlite' => $this->dumpSqlite($connection, $targetPath),
            default => throw BackupException::unsupportedDriver($driver),
        };
    }

    protected function dumpMysql(array $connection, string $targetPath): void
    {
        $dumper = new BackupDumper();
        $pdo = DB::connection()->getPdo();
        $dumper->start($targetPath, $pdo);
    }

    protected function dumpPostgres(array $connection, string $targetPath): void
    {
        $dumper = new BackupDumper();
        $pdo = DB::connection()->getPdo();
        $dumper->start($targetPath, $pdo);
    }

    protected function dumpSqlite(array $connection, string $targetPath): void
    {
        $database = $connection['database'];
        if (! str_starts_with($database, '/')) {
            $database = database_path($database);
        }
        File::copy($database, $targetPath);
    }

    protected function archiveStorage(string $targetPath): void
    {
        $source = storage_path('app');
        $exclude = [storage_path('app/backups')];
        ZipHelper::zipDirectory($source, $targetPath, $exclude);
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
