<?php

namespace App\Services;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Service responsible for importing website content from exported ZIP files.
 * Completely replaces current content and resets auto-increment IDs.
 */
class WebsiteImportService
{
    /**
     * Database tables that will be imported.
     * Must match the export order for proper dependency handling.
     *
     * @var array<string>
     */
    private array $importTables = [
        // Core reference tables (no dependencies)
        // 'users',
        'translation_keys',
        'pictures',
        'optimized_pictures',
        'custom_emojis',
        'people',
        'tags',
        'videos',
        'social_media_links',
        'technologies', // moved after pictures since it depends on pictures via icon_picture_id
        'technology_experiences',
        'certifications',
        'experiences',

        // Blog tables (reference tables)
        'blog_categories',
        'blog_content_markdown',
        'blog_content_galleries',
        'blog_content_videos',

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
        'blog_content_gallery_pictures',

        // Metadata tables
        'user_agent_metadata',
        'ip_address_metadata',
    ];

    /**
     * Import website data from a ZIP file.
     *
     * @param  string  $zipPath  Path to the export ZIP file
     * @return array<string, mixed> Import statistics
     *
     * @throws Throwable
     */
    public function importWebsite(string $zipPath): array
    {
        set_time_limit(3600);
        if (! file_exists($zipPath)) {
            throw new RuntimeException('Import file does not exist');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open ZIP file');
        }

        $stats = [
            'tables_imported' => 0,
            'records_imported' => 0,
            'files_imported' => 0,
            'import_date' => now()->toISOString(),
        ];

        try {
            $this->validateExportStructure($zip);
            $this->clearExistingData();

            $dbStats = $this->importDatabase($zip);
            $stats['tables_imported'] = $dbStats['tables'];
            $stats['records_imported'] = $dbStats['records'];
            $stats['files_imported'] = $this->importFiles($zip);

            $this->resetAutoIncrements();

            $zip->close();

            return $stats;
        } catch (Throwable $e) {
            $zip->close();
            Log::error('Import failed');
            Log::error($e->getMessage(), $e->getTrace());
            throw new RuntimeException('Import failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that the ZIP file has the expected export structure.
     *
     * @throws RuntimeException
     */
    private function validateExportStructure(ZipArchive $zip): void
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
     * Clear all existing data from the database.
     * This completely empties the content tables.
     */
    private function clearExistingData(): void
    {
        // Handle different database types
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            // Clear tables in reverse order to handle dependencies
            $reverseTables = array_reverse($this->importTables);

            foreach ($reverseTables as $table) {
                if ($this->tableExists($table)) {
                    // Don't clear the users table in production to avoid locking out admins
                    if ($table === 'users' && app()->environment('production')) {
                        continue;
                    }

                    if ($driver === 'sqlite') {
                        DB::table($table)->delete();
                    } else {
                        DB::table($table)->truncate();
                    }
                }
            }
        } finally {
            // Re-enable foreign key constraints
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    /**
     * Import database tables from the ZIP file.
     *
     * @return array<string, int> Statistics about imported data
     *
     * @throws RuntimeException
     */
    private function importDatabase(ZipArchive $zip): array
    {
        $stats = ['tables' => 0, 'records' => 0];

        foreach ($this->importTables as $table) {
            $fileName = "database/{$table}.json";
            $content = $zip->getFromName($fileName);

            if ($content === false) {
                // Skip missing tables (they might not exist in the export)
                continue;
            }

            $data = json_decode($content, true);

            if ($data === null) {
                throw new RuntimeException("Invalid JSON data for table: {$table}");
            }

            if (! empty($data)) {
                // Skip users table in production to avoid locking out admins
                if ($table === 'users' && app()->environment('production')) {
                    continue;
                }

                // Convert objects to arrays for database insertion
                $data = array_map(function ($row) {
                    return (array) $row;
                }, $data);

                DB::table($table)->insert($data);
                $stats['records'] += count($data);
            }

            $stats['tables']++;
        }

        return $stats;
    }

    /**
     * Import files from the ZIP to public storage.
     *
     * @return int Number of files imported
     */
    private function importFiles(ZipArchive $zip): int
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
        $this->clearLocalDiskFiles($localDisk);

        // Import files from ZIP
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if ($fileName !== false && str_starts_with($fileName, 'files/')) {
                $relativePath = substr($fileName, 6); // Remove 'files/' prefix

                if (! empty($relativePath) && ! str_ends_with($fileName, '/')) {
                    $content = $zip->getFromName($fileName);

                    if ($content !== false) {
                        // Ensure directory exists
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
    private function clearLocalDiskFiles(Filesystem $localDisk): void
    {
        // Clear temp directory (where exports are stored)
        if ($localDisk->exists('temp')) {
            $tempFiles = $localDisk->allFiles('temp');
            foreach ($tempFiles as $file) {
                $localDisk->delete($file);
            }
        }

        // Clear any other temporary files
        $allFiles = $localDisk->allFiles('');
        foreach ($allFiles as $file) {
            // Skip essential directories/files
            if (! str_starts_with($file, '.gitignore') && ! str_starts_with($file, 'framework/')) {
                $localDisk->delete($file);
            }
        }
    }

    /**
     * Reset auto-increment values for all tables.
     * This ensures IDs start from 1 after import.
     */
    private function resetAutoIncrements(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        foreach ($this->importTables as $table) {
            if ($this->tableExists($table)) {
                try {
                    // Skip users table in production
                    if ($table === 'users' && app()->environment('production')) {
                        continue;
                    }

                    if ($driver === 'mysql') {
                        DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                    } elseif ($driver === 'sqlite') {
                        // SQLite auto-increment reset happens automatically when table is cleared
                        // Update the sqlite_sequence table if it exists
                        DB::statement("UPDATE sqlite_sequence SET seq = 0 WHERE name = '{$table}'");
                    }
                } catch (Exception $e) {
                    // Some tables might not have auto-increment columns, ignore errors
                    continue;
                }
            }
        }
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
     * Get import metadata from a ZIP file without importing.
     *
     * @return array<string, mixed>|null
     */
    public function getImportMetadata(string $zipPath): ?array
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
    public function validateImportFile(string $zipPath): array
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
            $this->validateExportStructure($zip);
            $results['metadata'] = $this->getImportMetadata($zipPath);
            $results['valid'] = true;
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        } finally {
            $zip->close();
        }

        return $results;
    }

    /**
     * Get the list of tables that will be imported.
     *
     * @return array<string>
     */
    public function getImportTables(): array
    {
        return $this->importTables;
    }
}
