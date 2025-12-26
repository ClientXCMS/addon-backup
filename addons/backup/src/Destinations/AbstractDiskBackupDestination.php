<?php

namespace App\Addons\Backup\Destinations;

use App\Addons\Backup\Contracts\BackupDestination;
use App\Addons\Backup\DTO\BackupDescriptor;
use App\Addons\Backup\Exceptions\BackupException;
use App\Addons\Backup\Support\ZipHelper;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class AbstractDiskBackupDestination implements BackupDestination
{
    public function __construct(
        protected string $disk,
        protected string $providerName,
        protected string $basePath = 'backups'
    ) {}

    public function store(string $pathToBackupDirectory): string
    {
        $metadataPath = $pathToBackupDirectory . '/backup.json';
        if (! File::exists($metadataPath)) {
            throw new BackupException('Backup metadata file [backup.json] is missing.');
        }

        $metadata = json_decode(File::get($metadataPath), true) ?? [];
        $identifier = $metadata['uuid'] ?? (string) Str::uuid();
        $metadata['uuid'] = $identifier;
        $metadata['provider'] = $this->providerName;
        $metadata['disk'] = $this->disk;
        $metadata['filename'] = $metadata['filename'] ?? $this->buildFilename($identifier);

        $archivePath = $pathToBackupDirectory . '.zip';
        ZipHelper::zipDirectory($pathToBackupDirectory, $archivePath);

        $filesystem = $this->filesystem();
        $stream = fopen($archivePath, 'r');
        $filesystem->put($this->zipPath($identifier), $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $filesystem->put($this->metadataPath($identifier), json_encode($metadata, JSON_PRETTY_PRINT));

        File::delete($archivePath);

        return $identifier;
    }

    public function listBackups(): array
    {
        $filesystem = $this->filesystem();
        $files = collect($filesystem->files($this->basePath))
            ->filter(fn($file) => str_ends_with($file, '.json'));

        $backups = [];
        foreach ($files as $file) {
            $payload = json_decode($filesystem->get($file), true);
            if (! is_array($payload)) {
                continue;
            }
            $identifier = $payload['uuid'] ?? pathinfo($file, PATHINFO_FILENAME);
            if (! $filesystem->exists($this->zipPath($identifier))) {
                continue;
            }
            $createdAt = isset($payload['created_at']) ? Carbon::parse($payload['created_at']) : now();
            $size = (int) $filesystem->size($this->zipPath($identifier));
            $backups[] = new BackupDescriptor(
                $identifier,
                $payload['filename'] ?? $this->buildFilename($identifier),
                $createdAt,
                $payload['type'] ?? 'full',
                $size,
                $payload['provider'] ?? $this->providerName,
                $payload['disk'] ?? $this->disk,
                $payload
            );
        }

        usort($backups, fn(BackupDescriptor $a, BackupDescriptor $b) => $b->createdAt <=> $a->createdAt);

        return $backups;
    }

    public function getBackup(string $identifier): ?BackupDescriptor
    {
        return collect($this->listBackups())->firstWhere('identifier', $identifier);
    }

    public function deleteBackup(string $identifier): void
    {
        $filesystem = $this->filesystem();
        $filesystem->delete($this->zipPath($identifier));
        $filesystem->delete($this->metadataPath($identifier));
    }

    public function downloadBackup(string $identifier, string $localTargetPath): void
    {
        $filesystem = $this->filesystem();
        if (! $filesystem->exists($this->zipPath($identifier))) {
            throw new BackupException('Backup archive not found for identifier ' . $identifier);
        }

        File::ensureDirectoryExists(dirname($localTargetPath));
        $stream = $filesystem->readStream($this->zipPath($identifier));
        if (! $stream) {
            throw new BackupException('Unable to read remote backup stream.');
        }

        $local = fopen($localTargetPath, 'w+b');
        stream_copy_to_stream($stream, $local);
        fclose($stream);
        fclose($local);
    }

    protected function filesystem(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    protected function zipPath(string $identifier): string
    {
        return $this->basePath . '/' . $this->buildIdentifier($identifier) . '.zip';
    }

    protected function metadataPath(string $identifier): string
    {
        return $this->basePath . '/' . $this->buildIdentifier($identifier) . '.json';
    }

    protected function buildFilename(string $identifier): string
    {
        return 'backup-' . $identifier . '.zip';
    }

    protected function buildIdentifier(string $identifier): string
    {
        return trim($identifier);
    }
}
