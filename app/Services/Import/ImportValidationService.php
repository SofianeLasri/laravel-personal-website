<?php

declare(strict_types=1);

namespace App\Services\Import;

use Exception;
use RuntimeException;
use ZipArchive;

/**
 * Service for validating import files
 */
class ImportValidationService
{
    /**
     * Validate that the ZIP file has the expected export structure.
     *
     * @throws RuntimeException
     */
    public function validateStructure(ZipArchive $zip): void
    {
        // Check for metadata file
        if ($zip->locateName('export-metadata.json') === false) {
            throw new RuntimeException('Invalid export file: missing metadata');
        }

        // Check for database directory
        $hasDatabaseFiles = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);
            if ($fileName !== false && str_starts_with($fileName, 'database/')) {
                $hasDatabaseFiles = true;
                break;
            }
        }

        if (! $hasDatabaseFiles) {
            throw new RuntimeException('Invalid export file: missing database files');
        }
    }

    /**
     * Get import metadata from a ZIP file without importing.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $zipPath): ?array
    {
        if (! file_exists($zipPath)) {
            return null;
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }

        $content = $zip->getFromName('export-metadata.json');
        $zip->close();

        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    /**
     * Validate an export file before import.
     *
     * @return array<string, mixed> Validation results
     */
    public function validateFile(string $zipPath): array
    {
        $results = [
            'valid' => false,
            'errors' => [],
            'metadata' => null,
        ];

        if (! file_exists($zipPath)) {
            $results['errors'][] = 'File does not exist';

            return $results;
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            $results['errors'][] = 'Cannot open ZIP file';

            return $results;
        }

        try {
            $this->validateStructure($zip);
            $results['metadata'] = $this->getMetadata($zipPath);
            $results['valid'] = true;
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        } finally {
            $zip->close();
        }

        return $results;
    }
}
