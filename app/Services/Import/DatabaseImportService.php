<?php

declare(strict_types=1);

namespace App\Services\Import;

use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Service for importing database tables from ZIP archives
 */
class DatabaseImportService
{
    /**
     * Database tables that will be imported.
     *
     * @var array<string>
     */
    private array $importTables = [
        'translation_keys',
        'pictures',
        'optimized_pictures',
        'custom_emojis',
        'people',
        'tags',
        'videos',
        'social_media_links',
        'technologies',
        'technology_experiences',
        'certifications',
        'experiences',
        'blog_categories',
        'content_markdowns',
        'content_galleries',
        'content_videos',
        'translations',
        'creations',
        'features',
        'screenshots',
        'creation_drafts',
        'creation_draft_features',
        'creation_draft_screenshots',
        'blog_posts',
        'blog_post_drafts',
        'blog_post_contents',
        'blog_post_draft_contents',
        'creation_technology',
        'creation_person',
        'creation_tag',
        'creation_video',
        'creation_draft_technology',
        'creation_draft_person',
        'creation_draft_tag',
        'creation_draft_video',
        'content_gallery_pictures',
        'user_agent_metadata',
        'ip_address_metadata',
    ];

    /**
     * Clear all existing data from the database.
     */
    public function clearData(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            $reverseTables = array_reverse($this->importTables);

            foreach ($reverseTables as $table) {
                if ($this->tableExists($table)) {
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
    public function import(ZipArchive $zip): array
    {
        $stats = ['tables' => 0, 'records' => 0];

        foreach ($this->importTables as $table) {
            $fileName = "database/{$table}.json";
            $content = $zip->getFromName($fileName);

            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);

            if ($data === null) {
                throw new RuntimeException("Invalid JSON data for table: {$table}");
            }

            if (! empty($data)) {
                if ($table === 'users' && app()->environment('production')) {
                    continue;
                }

                $data = array_map(fn ($row) => (array) $row, $data);

                DB::table($table)->insert($data);
                $stats['records'] += count($data);
            }

            $stats['tables']++;
        }

        return $stats;
    }

    /**
     * Check if a database table exists.
     */
    public function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get the list of tables that will be imported.
     *
     * @return array<string>
     */
    public function getTables(): array
    {
        return $this->importTables;
    }
}
