<?php

namespace App\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use RuntimeException;
use ZipArchive;

/**
 * Service responsible for exporting all website content (database + files) to a ZIP file.
 * Creates a complete backup that can be imported to restore the full website state.
 */
class WebsiteExportService
{
    /**
     * Database tables that contain the website content.
     * Ordered by dependencies to ensure proper import order.
     *
     * @var array<string>
     */
    private array $exportTables = [
        // Core reference tables (no dependencies)
        // 'users',
        'translation_keys',
        'technologies',
        'people',
        'tags',
        'pictures',
        'optimized_pictures',
        'videos',
        'social_media_links',
        'technology_experiences',
        'certifications',
        'experiences',

        // Content tables (depend on reference tables)
        'translations',
        'creations',
        'features',
        'screenshots',
        'creation_drafts',
        'creation_draft_features',
        'creation_draft_screenshots',

        // Pivot tables (depend on all other tables)
        'creation_technology',
        'creation_person',
        'creation_tag',
        'creation_video',
        'creation_draft_technology',
        'creation_draft_person',
        'creation_draft_tag',
        'creation_draft_video',

        // Metadata tables
        'user_agent_metadata',
        'ip_address_metadata',
    ];

    /**
     * Export the complete website data to a ZIP file.
     *
     * @return string The path to the generated ZIP file
     *
     * @throws RuntimeException If export fails
     */
    public function exportWebsite(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = "website-export-{$timestamp}.zip";
        $tempFullPath = Storage::disk('local')->path("temp/{$fileName}");
        Log::debug("temps full path: $tempFullPath");
        Storage::makeDirectory('temp');

        $zip = new ZipArchive;

        if ($zip->open($tempFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ZIP file');
        }

        try {
            $this->exportDatabase($zip);
            $this->exportFiles($zip);
            $this->addExportMetadata($zip);

            $zip->close();

            return "temp/{$fileName}";
        } catch (Exception $e) {
            $zip->close();
            if (file_exists($tempFullPath)) {
                unlink($tempFullPath);
            }
            throw new RuntimeException('Export failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export database tables to JSON files in the ZIP.
     *
     * @throws RuntimeException
     */
    private function exportDatabase(ZipArchive $zip): void
    {
        foreach ($this->exportTables as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            $data = DB::table($table)->get()->toArray();
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                throw new RuntimeException("Failed to encode data for table: {$table}");
            }

            $zip->addFromString("database/{$table}.json", $json);
        }
    }

    /**
     * Export public storage files to the ZIP.
     */
    private function exportFiles(ZipArchive $zip): void
    {
        $publicDisk = Storage::disk('public');
        $files = $this->getAllFiles($publicDisk, '');

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
    private function getAllFiles(Filesystem $disk, string $directory): array
    {
        return $disk->allFiles($directory);
    }

    /**
     * Add export metadata to the ZIP.
     */
    private function addExportMetadata(ZipArchive $zip): void
    {
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => config('database.connections.'.config('database.default').'.database'),
            'tables_exported' => $this->exportTables,
            'files_count' => $this->countExportedFiles(),
        ];

        $metadataJson = json_encode($metadata, JSON_PRETTY_PRINT);
        if ($metadataJson !== false) {
            $zip->addFromString('export-metadata.json', $metadataJson);
        }
    }

    /**
     * Count the number of files that will be exported.
     */
    private function countExportedFiles(): int
    {
        $publicDisk = Storage::disk('public');

        return count($publicDisk->allFiles(''));
    }

    /**
     * Check if a database table exists.
     */
    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the list of tables that will be exported.
     *
     * @return array<string>
     */
    public function getExportTables(): array
    {
        return $this->exportTables;
    }

    /**
     * Clean up old export files.
     *
     * @param  int  $keepDays  Number of days to keep export files
     * @return int Number of files deleted
     */
    public function cleanupOldExports(int $keepDays = 7): int
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
}
