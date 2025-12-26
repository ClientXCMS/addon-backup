<?php

namespace App\Addons\Backup\Destinations;

class GoogleDriveBackupDestination extends AbstractDiskBackupDestination
{
    public function __construct(string $disk, string $basePath = 'backups')
    {
        parent::__construct($disk, 'google', $basePath);
    }
}
