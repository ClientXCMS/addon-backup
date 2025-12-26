<?php

namespace App\Addons\Backup\Destinations;

class LocalBackupDestination extends AbstractDiskBackupDestination
{
    public function __construct(string $disk, string $basePath = 'backups')
    {
        parent::__construct($disk, 'local', $basePath);
    }
}
