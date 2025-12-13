<?php

declare(strict_types=1);

namespace App\Services\Export;

use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Service for exporting database tables
 */
class DatabaseExportService
{
    /**
     * Database tables that contain the website content.
     * Ordered by dependencies to ensure proper import order.
     *
     * @var array<string>
     */
    private array $exportTables = [
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
     * Export database tables to JSON files in the ZIP.
     *
     * @throws RuntimeException
     */
    public function export(ZipArchive $zip): void
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
     * Get the list of tables that will be exported.
     *
     * @return array<string>
     */
    public function getTables(): array
    {
        return $this->exportTables;
    }
}
