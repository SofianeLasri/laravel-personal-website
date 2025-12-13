<?php

declare(strict_types=1);

namespace App\Services\Export;

use ZipArchive;

/**
 * Service for managing export metadata
 */
readonly class ExportMetadataService
{
    public function __construct(
        private DatabaseExportService $databaseExport,
        private FileExportService $fileExport
    ) {}

    /**
     * Add export metadata to the ZIP.
     */
    public function create(ZipArchive $zip): void
    {
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => config('database.connections.'.config('database.default').'.database'),
            'tables_exported' => $this->databaseExport->getTables(),
            'files_count' => $this->fileExport->countFiles(),
        ];

        $metadataJson = json_encode($metadata, JSON_PRETTY_PRINT);
        if ($metadataJson !== false) {
            $zip->addFromString('export-metadata.json', $metadataJson);
        }
    }

    /**
     * Read metadata from a ZIP file.
     *
     * @return array<string, mixed>|null
     */
    public function read(ZipArchive $zip): ?array
    {
        $content = $zip->getFromName('export-metadata.json');

        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }
}
