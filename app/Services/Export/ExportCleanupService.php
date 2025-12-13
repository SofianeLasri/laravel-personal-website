<?php

declare(strict_types=1);

namespace App\Services\Export;

use Illuminate\Support\Facades\Storage;

/**
 * Service for cleaning up old export files
 */
class ExportCleanupService
{
    /**
     * Clean up old export files.
     *
     * @param  int  $keepDays  Number of days to keep export files
     * @return int Number of files deleted
     */
    public function cleanup(int $keepDays = 7): int
    {
        if (! Storage::directoryExists('temp')) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = now()->subDays($keepDays)->timestamp;

        $files = Storage::allFiles('temp');
        $exportFiles = array_filter($files, function ($file) {
            return str_contains($file, 'website-export-') && str_ends_with($file, '.zip');
        });

        foreach ($exportFiles as $file) {
            if (Storage::lastModified($file) < $cutoffTime) {
                Storage::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get list of existing export files with their metadata.
     *
     * @return array<array{path: string, size: int, modified: int}>
     */
    public function listExports(): array
    {
        if (! Storage::directoryExists('temp')) {
            return [];
        }

        $files = Storage::allFiles('temp');
        $exportFiles = array_filter($files, function ($file) {
            return str_contains($file, 'website-export-') && str_ends_with($file, '.zip');
        });

        $result = [];
        foreach ($exportFiles as $file) {
            $result[] = [
                'path' => $file,
                'size' => Storage::size($file),
                'modified' => Storage::lastModified($file),
            ];
        }

        // Sort by modification date (newest first)
        usort($result, fn ($a, $b) => $b['modified'] - $a['modified']);

        return $result;
    }
}
