<?php

namespace App\Addons\Backup\Exceptions;

use RuntimeException;

class BackupException extends RuntimeException
{
    public static function unsupportedDriver(string $driver): self
    {
        return new self("Unsupported database driver [{$driver}] for backup operations.");
    }

    public static function missingArtifact(string $name): self
    {
        return new self("Required backup artifact [{$name}] was not found inside the archive.");
    }
}
