<?php

namespace App\Addons\Backup\Contracts;

use App\Addons\Backup\DTO\BackupDescriptor;

interface BackupDestination
{
    /**
     * Store the prepared backup directory into the destination.
     *
     * @param  string  $pathToBackupDirectory  Absolute path to the folder that contains the backup artifacts.
     * @return string Identifier generated for the stored backup.
     */
    public function store(string $pathToBackupDirectory): string;

    /**
     * List available backups.
     *
     * @return BackupDescriptor[]
     */
    public function listBackups(): array;

    /**
     * Retrieve a single backup descriptor.
     */
    public function getBackup(string $identifier): ?BackupDescriptor;

    /**
     * Delete the backup and its metadata.
     */
    public function deleteBackup(string $identifier): void;

    /**
     * Download the backup archive locally.
     */
    public function downloadBackup(string $identifier, string $localTargetPath): void;
}
