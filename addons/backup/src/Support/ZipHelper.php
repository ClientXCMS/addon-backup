<?php

namespace App\Addons\Backup\Support;

use App\Addons\Backup\Exceptions\BackupException;
use Illuminate\Support\Facades\File;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use FilesystemIterator;

class ZipHelper
{
    public static function zipDirectory(string $directory, string $zipPath, array $excludedPaths = []): void
    {
        $zip = new ZipArchive();
        File::ensureDirectoryExists(dirname($zipPath));
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Unable to create backup archive.');
        }

        $root = realpath($directory);
        if ($root === false) {
            throw new BackupException('Unable to access directory '.$directory);
        }

        $excludes = array_values(array_filter(array_map(
            fn ($path) => $path ? realpath($path) : null,
            $excludedPaths
        )));

        $directoryIterator = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directoryIterator, function ($current) use ($excludes) {
            $filePath = $current->getRealPath();
            if ($filePath === false) {
                return false;
            }

            foreach ($excludes as $exclude) {
                if (str_starts_with($filePath, $exclude)) {
                    return false;
                }
            }

            return true;
        });

        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            $filePath = $fileInfo->getRealPath();
            if ($filePath === false) {
                continue;
            }
            $relativePath = trim(str_replace($root, '', $filePath), DIRECTORY_SEPARATOR);
            if ($relativePath === '') {
                continue;
            }

            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    public static function extractZip(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new BackupException('Unable to open backup archive.');
        }

        File::ensureDirectoryExists($destination);
        $zip->extractTo($destination);
        $zip->close();
    }
}
