<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Export;

use App\Models\Technology;
use App\Models\TranslationKey;
use App\Services\Export\DatabaseExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(DatabaseExportService::class)]
class DatabaseExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseExportService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DatabaseExportService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-export-'.uniqid().'.zip';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_exports_database_tables_to_zip(): void
    {
        // Create some test data
        TranslationKey::factory()->count(3)->create();
        Technology::factory()->count(2)->create();

        $expectedTranslationKeys = TranslationKey::count();
        $expectedTechnologies = Technology::count();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        // Check that translation_keys table was exported
        $translationKeysJson = $zip->getFromName('database/translation_keys.json');
        $this->assertNotFalse($translationKeysJson);
        $translationKeys = json_decode($translationKeysJson, true);
        $this->assertCount($expectedTranslationKeys, $translationKeys);

        // Check that technologies table was exported
        $technologiesJson = $zip->getFromName('database/technologies.json');
        $this->assertNotFalse($technologiesJson);
        $technologies = json_decode($technologiesJson, true);
        $this->assertCount($expectedTechnologies, $technologies);

        $zip->close();
    }

    #[Test]
    public function it_exports_empty_tables(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        // Check that an existing table was exported even if empty
        $translationKeysJson = $zip->getFromName('database/translation_keys.json');
        $this->assertNotFalse($translationKeysJson);
        $translationKeys = json_decode($translationKeysJson, true);
        $this->assertIsArray($translationKeys);
        $this->assertEmpty($translationKeys);

        $zip->close();
    }

    #[Test]
    public function it_skips_non_existent_tables(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        // This should not throw even if some tables don't exist
        $this->service->export($zip);

        $zip->close();

        $this->assertFileExists($this->tempZipPath);
    }

    #[Test]
    public function it_checks_if_table_exists(): void
    {
        // A table that should exist after migrations
        $this->assertTrue($this->service->tableExists('translation_keys'));

        // A table that should not exist
        $this->assertFalse($this->service->tableExists('non_existent_table_xyz'));
    }

    #[Test]
    public function it_returns_list_of_export_tables(): void
    {
        $tables = $this->service->getTables();

        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);
        $this->assertContains('translation_keys', $tables);
        $this->assertContains('technologies', $tables);
        $this->assertContains('pictures', $tables);
        $this->assertContains('creations', $tables);
    }

    #[Test]
    public function it_exports_tables_in_correct_format(): void
    {
        $translationKey = TranslationKey::factory()->create([
            'key' => 'test.key.example',
        ]);

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $json = $zip->getFromName('database/translation_keys.json');
        $data = json_decode($json, true);

        $this->assertCount(1, $data);
        $this->assertEquals('test.key.example', $data[0]['key']);

        $zip->close();
    }

    #[Test]
    public function it_exports_with_proper_json_encoding(): void
    {
        // Create data with unicode characters
        $technology = Technology::factory()->create([
            'name' => 'Tëst Tëchnölögy',
        ]);

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $json = $zip->getFromName('database/technologies.json');
        $data = json_decode($json, true);

        // Verify unicode was preserved (JSON_UNESCAPED_UNICODE flag)
        $this->assertEquals('Tëst Tëchnölögy', $data[0]['name']);

        $zip->close();
    }

    #[Test]
    public function it_includes_all_expected_tables(): void
    {
        $tables = $this->service->getTables();

        // Verify critical tables are in the list
        $expectedTables = [
            'translation_keys',
            'translations',
            'technologies',
            'pictures',
            'optimized_pictures',
            'videos',
            'creations',
            'creation_drafts',
            'blog_posts',
            'blog_post_drafts',
        ];

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tables, "Expected table '{$table}' not found in export list");
        }
    }
}
