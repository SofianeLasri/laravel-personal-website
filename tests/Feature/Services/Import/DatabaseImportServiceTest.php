<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Import;

use App\Models\Technology;
use App\Models\TranslationKey;
use App\Services\Import\DatabaseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(DatabaseImportService::class)]
class DatabaseImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseImportService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DatabaseImportService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-import-'.uniqid().'.zip';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_imports_database_tables_from_zip(): void
    {
        // Create a ZIP with database data
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        // translation_keys table only has id and key columns
        $translationKeysData = [
            ['id' => 100, 'key' => 'test.key.one'],
            ['id' => 101, 'key' => 'test.key.two'],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));
        $zip->close();

        // Import the data
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $stats = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(1, $stats['tables']);
        $this->assertEquals(2, $stats['records']);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.key.one']);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.key.two']);
    }

    #[Test]
    public function it_clears_data_from_tables(): void
    {
        // Create some data
        TranslationKey::factory()->count(5)->create();
        $this->assertEquals(5, TranslationKey::count());

        // Clear the data
        $this->service->clearData();

        $this->assertEquals(0, TranslationKey::count());
    }

    #[Test]
    public function it_skips_non_existent_tables_in_zip(): void
    {
        // Create a ZIP with a file for a non-existent table
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('database/non_existent_table.json', '[]');
        $zip->addFromString('database/translation_keys.json', json_encode([
            ['id' => 1, 'key' => 'test.key'],
        ]));
        $zip->close();

        // Import should not throw
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $stats = $this->service->import($zip);

        $zip->close();

        // Should only import the valid table
        $this->assertEquals(1, $stats['tables']);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.key']);
    }

    #[Test]
    public function it_handles_empty_tables_in_zip(): void
    {
        // Create a ZIP with empty table data
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('database/translation_keys.json', '[]');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $stats = $this->service->import($zip);

        $zip->close();

        // Empty tables should still be counted
        $this->assertEquals(1, $stats['tables']);
        $this->assertEquals(0, $stats['records']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json(): void
    {
        // Create a ZIP with invalid JSON
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('database/translation_keys.json', 'not valid json');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON data for table: translation_keys');

        $this->service->import($zip);

        $zip->close();
    }

    #[Test]
    public function it_checks_if_table_exists(): void
    {
        $this->assertTrue($this->service->tableExists('translation_keys'));
        $this->assertFalse($this->service->tableExists('non_existent_xyz'));
    }

    #[Test]
    public function it_returns_list_of_import_tables(): void
    {
        $tables = $this->service->getTables();

        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);
        $this->assertContains('translation_keys', $tables);
        $this->assertContains('technologies', $tables);
        $this->assertContains('pictures', $tables);
    }

    #[Test]
    public function it_imports_multiple_tables(): void
    {
        // Create a ZIP with multiple tables
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $translationKeysData = [
            ['id' => 1, 'key' => 'test.key'],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));

        // tags table has: id, name, slug, created_at, updated_at
        $tagsData = [
            [
                'id' => 1,
                'name' => 'Test Tag',
                'slug' => 'test-tag',
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ],
        ];
        $zip->addFromString('database/tags.json', json_encode($tagsData));
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $stats = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(2, $stats['tables']);
        $this->assertEquals(2, $stats['records']);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.key']);
        $this->assertDatabaseHas('tags', ['name' => 'Test Tag']);
    }

    #[Test]
    public function it_clears_data_in_reverse_order(): void
    {
        // Create data with relationships
        $translationKey = TranslationKey::factory()->create();
        Technology::factory()->create([
            'description_translation_key_id' => $translationKey->id,
        ]);

        // This should not throw foreign key constraint errors
        $this->service->clearData();

        $this->assertEquals(0, Technology::count());
        $this->assertEquals(0, TranslationKey::count());
    }

    #[Test]
    public function it_preserves_original_ids(): void
    {
        // Create a ZIP with specific IDs
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $translationKeysData = [
            ['id' => 42, 'key' => 'test.key.42'],
            ['id' => 100, 'key' => 'test.key.100'],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->service->import($zip);

        $zip->close();

        $this->assertDatabaseHas('translation_keys', ['id' => 42, 'key' => 'test.key.42']);
        $this->assertDatabaseHas('translation_keys', ['id' => 100, 'key' => 'test.key.100']);
    }
}
