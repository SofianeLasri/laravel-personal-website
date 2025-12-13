<?php

namespace App\Services;

use App\Services\Export\DatabaseExportService;
use App\Services\Export\ExportCleanupService;
use App\Services\Export\ExportMetadataService;
use App\Services\Export\FileExportService;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Service responsible for exporting all website content (database + files) to a ZIP file.
 * Creates a complete backup that can be imported to restore the full website state.
 *
 * @deprecated This service is being refactored. Use the specialized services instead:
 * - DatabaseExportService for database export
 * - FileExportService for file export
 * - ExportMetadataService for export metadata
 * - ExportCleanupService for cleanup of old exports
 */
class WebsiteExportService
{
    private ?DatabaseExportService $databaseExport;

    private ?FileExportService $fileExport;

    private ?ExportMetadataService $metadataService;

    private ?ExportCleanupService $cleanupService;

    public function __construct(
        ?DatabaseExportService $databaseExport = null,
        ?FileExportService $fileExport = null,
        ?ExportMetadataService $metadataService = null,
        ?ExportCleanupService $cleanupService = null
    ) {
        $this->databaseExport = $databaseExport;
        $this->fileExport = $fileExport;
        $this->metadataService = $metadataService;
        $this->cleanupService = $cleanupService;
    }

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
        'custom_emojis',
        'videos',
        'social_media_links',
        'technology_experiences',
        'certifications',
        'experiences',

        // Blog tables (reference tables)
        'blog_categories',
        'content_markdowns',
        'content_galleries',
        'content_videos',

        // Content tables (depend on reference tables)
        'translations',
        'creations',
        'features',
        'screenshots',
        'creation_drafts',
        'creation_draft_features',
        'creation_draft_screenshots',

        // Blog content tables
        'blog_posts',
        'blog_post_drafts',
        'blog_post_contents',
        'blog_post_draft_contents',

        // Pivot tables (depend on all other tables)
        'creation_technology',
        'creation_person',
        'creation_tag',
        'creation_video',
        'creation_draft_technology',
        'creation_draft_person',
        'creation_draft_tag',
        'creation_draft_video',
        'content_gallery_pictures',

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
     * @deprecated Use DatabaseExportService::export() instead
     *
     * @throws RuntimeException
     */
    private function exportDatabase(ZipArchive $zip): void
    {
        // Delegate to new service if available
        if ($this->databaseExport) {
            $this->databaseExport->export($zip);

            return;
        }

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
     *
     * @deprecated Use FileExportService::export() instead
     */
    private function exportFiles(ZipArchive $zip): void
    {
        // Delegate to new service if available
        if ($this->fileExport) {
            $this->fileExport->export($zip);

            return;
        }

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
    private function getAllFiles(Filesystem $disk): array
    {
        return $disk->allFiles('');
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
        } catch (Exception) {
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
     * @deprecated Use ExportCleanupService::cleanup() instead
     *
     * @param  int  $keepDays  Number of days to keep export files
     * @return int Number of files deleted
     */
    public function cleanupOldExports(int $keepDays = 7): int
    {
        // Delegate to new service if available
        if ($this->cleanupService) {
            return $this->cleanupService->cleanup($keepDays);
        }

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
