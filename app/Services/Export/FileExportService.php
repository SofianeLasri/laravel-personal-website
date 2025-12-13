<?php

declare(strict_types=1);

namespace App\Services\Export;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Service for exporting files to a ZIP archive
 */
class FileExportService
{
    /**
     * Export public storage files to the ZIP.
     */
    public function export(ZipArchive $zip): void
    {
        $publicDisk = Storage::disk('public');
        $files = $this->getAllFiles($publicDisk);

        foreach ($files as $file) {
            $content = $publicDisk->get($file);
            if ($content !== null) {
                $zip->addFromString("files/{$file}", $content);
            }
        }
    }

    /**
     * Get all files recursively from a disk.
     *
     * @return array<string>
     */
    public function getAllFiles(Filesystem $disk): array
    {
        return $disk->allFiles('');
    }

    /**
     * Count the number of files that will be exported.
     */
    public function countFiles(): int
    {
        $publicDisk = Storage::disk('public');

        return count($publicDisk->allFiles(''));
    }
}
