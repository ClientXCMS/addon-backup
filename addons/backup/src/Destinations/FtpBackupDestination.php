<?php

namespace App\Addons\Backup\Destinations;

class FtpBackupDestination extends AbstractDiskBackupDestination
{
    public function __construct(string $disk, string $basePath = 'backups')
    {
        parent::__construct($disk, 'ftp', $basePath);
    }
}
