<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Service for importing files from ZIP archives
 */
class FileImportService
{
    /**
     * Import files from the ZIP to public storage.
     *
     * @return int Number of files imported
     */
    public function import(ZipArchive $zip): int
    {
        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');
        $filesImported = 0;

        // Clear existing files from public disk
        $existingFiles = $publicDisk->allFiles('');
        foreach ($existingFiles as $file) {
            $publicDisk->delete($file);
        }

        // Clear existing files from local disk (temp exports, etc.)
        $this->clearLocal($localDisk);

        // Import files from ZIP
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if ($fileName !== false && str_starts_with($fileName, 'files/')) {
                $relativePath = substr($fileName, 6); // Remove 'files/' prefix

                if (! empty($relativePath) && ! str_ends_with($fileName, '/')) {
                    $content = $zip->getFromName($fileName);

                    if ($content !== false) {
                        $directory = dirname($relativePath);
                        if ($directory !== '.' && ! $publicDisk->exists($directory)) {
                            $publicDisk->makeDirectory($directory);
                        }

                        $publicDisk->put($relativePath, $content);
                        $filesImported++;
                    }
                }
            }
        }

        return $filesImported;
    }

    /**
     * Clear all files from the local disk (temp exports and other temporary files).
     */
    public function clearLocal(Filesystem $localDisk): void
    {
        if ($localDisk->exists('temp')) {
            $tempFiles = $localDisk->allFiles('temp');
            foreach ($tempFiles as $file) {
                $localDisk->delete($file);
            }
        }

        $allFiles = $localDisk->allFiles('');
        foreach ($allFiles as $file) {
            if (! str_starts_with($file, '.gitignore') && ! str_starts_with($file, 'framework/')) {
                $localDisk->delete($file);
            }
        }
    }
}
