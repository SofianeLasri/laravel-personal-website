<?php

namespace Tests\Unit\Services;

use App\Models\Picture;
use App\Models\Technology;
use App\Models\User;
use App\Services\WebsiteImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(WebsiteImportService::class)]
class WebsiteImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebsiteImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importService = new WebsiteImportService;
        Storage::fake('public');
    }

    #[Test]
    public function test_get_import_tables_returns_correct_tables(): void
    {
        $tables = $this->importService->getImportTables();

        $this->assertIsArray($tables);
        $this->assertContains('users', $tables);
        $this->assertContains('technologies', $tables);
        $this->assertContains('creations', $tables);
        $this->assertContains('pictures', $tables);

        $usersIndex = array_search('users', $tables);
        $creationTechnologyIndex = array_search('creation_technology', $tables);
        $this->assertLessThan($creationTechnologyIndex, $usersIndex);
    }

    #[Test]
    public function test_validate_import_file_accepts_valid_zip(): void
    {
        $zipPath = $this->createValidZipFile();

        $result = $this->importService->validateImportFile($zipPath);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['metadata']);

        unlink($zipPath);
    }

    #[Test]
    public function test_validate_import_file_rejects_invalid_zip(): void
    {
        $zipPath = $this->createInvalidZipFile();

        $result = $this->importService->validateImportFile($zipPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        unlink($zipPath);
    }

    #[Test]
    public function test_validate_import_file_handles_nonexistent_file(): void
    {
        $result = $this->importService->validateImportFile('/nonexistent/file.zip');

        $this->assertFalse($result['valid']);
        $this->assertContains('File does not exist', $result['errors']);
    }

    #[Test]
    public function test_get_import_metadata_returns_correct_data(): void
    {
        $zipPath = $this->createValidZipFile();

        $metadata = $this->importService->getImportMetadata($zipPath);

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('database_name', $metadata);
        $this->assertArrayHasKey('files_count', $metadata);

        unlink($zipPath);
    }

    #[Test]
    public function test_get_import_metadata_handles_invalid_file(): void
    {
        $metadata = $this->importService->getImportMetadata('/nonexistent/file.zip');

        $this->assertNull($metadata);
    }

    #[Test]
    public function test_import_website_replaces_existing_data(): void
    {
        Technology::factory()->create(['name' => 'Existing Tech']);
        Picture::factory()->create(['filename' => 'existing.jpg']);

        $this->assertDatabaseHas('technologies', ['name' => 'Existing Tech']);
        $this->assertDatabaseHas('pictures', ['filename' => 'existing.jpg']);

        $zipPath = $this->createValidZipFile();
        $stats = $this->importService->importWebsite($zipPath);

        $this->assertDatabaseMissing('technologies', ['name' => 'Existing Tech']);
        $this->assertDatabaseMissing('pictures', ['filename' => 'existing.jpg']);
        $this->assertDatabaseHas('technologies', ['name' => 'Imported Tech']);
        $this->assertDatabaseHas('pictures', ['filename' => 'imported.jpg']);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('tables_imported', $stats);
        $this->assertArrayHasKey('records_imported', $stats);
        $this->assertArrayHasKey('files_imported', $stats);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_restores_files(): void
    {
        Storage::disk('public')->put('existing-file.txt', 'existing content');
        $this->assertTrue(Storage::disk('public')->exists('existing-file.txt'));

        $zipPath = $this->createValidZipFileWithFiles();
        $stats = $this->importService->importWebsite($zipPath);

        $this->assertFalse(Storage::disk('public')->exists('existing-file.txt'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/imported-file.txt'));
        $this->assertEquals('imported content', Storage::disk('public')->get('uploads/imported-file.txt'));
        $this->assertEquals(1, $stats['files_imported']);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_preserves_file_structure(): void
    {
        $zipPath = $this->createZipFileWithNestedFiles();
        $this->importService->importWebsite($zipPath);

        $this->assertTrue(Storage::disk('public')->exists('uploads/images/test1.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/images/subfolder/test2.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('documents/test.pdf'));

        $this->assertEquals('image1', Storage::disk('public')->get('uploads/images/test1.jpg'));
        $this->assertEquals('image2', Storage::disk('public')->get('uploads/images/subfolder/test2.jpg'));
        $this->assertEquals('pdf content', Storage::disk('public')->get('documents/test.pdf'));

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_handles_empty_database(): void
    {
        $zipPath = $this->createValidZipFileEmptyDatabase();
        $stats = $this->importService->importWebsite($zipPath);

        $this->assertEquals(0, $stats['records_imported']);
        $this->assertGreaterThan(0, $stats['tables_imported']); // Should still process table files

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_handles_missing_tables(): void
    {
        $zipPath = $this->createPartialZipFile();
        $stats = $this->importService->importWebsite($zipPath);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('tables_imported', $stats);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_throws_exception_on_invalid_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Import file does not exist');

        $this->importService->importWebsite('/nonexistent/file.zip');
    }

    #[Test]
    public function test_import_website_throws_exception_on_corrupted_zip(): void
    {
        $corruptedZip = tempnam(sys_get_temp_dir(), 'corrupted').'.zip';
        file_put_contents($corruptedZip, 'not a zip file');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot open ZIP file');

        $this->importService->importWebsite($corruptedZip);

        unlink($corruptedZip);
    }

    #[Test]
    public function test_import_website_handles_invalid_json_data(): void
    {
        $zipPath = $this->createZipFileWithInvalidJson();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON data for table');

        $this->importService->importWebsite($zipPath);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_restores_relationships(): void
    {
        $zipPath = $this->createZipFileWithRelationships();
        $this->importService->importWebsite($zipPath);

        $this->assertDatabaseHas('technologies', ['name' => 'Laravel']);
        $this->assertDatabaseHas('translation_keys', ['key' => 'technology.laravel.description']);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_resets_auto_increments(): void
    {
        Technology::factory()->create(['id' => 100, 'name' => 'High ID Tech']);

        $zipPath = $this->createValidZipFile();
        $this->importService->importWebsite($zipPath);

        $technology = Technology::first();
        $this->assertEquals(1, $technology->id);

        unlink($zipPath);
    }

    #[Test]
    public function test_import_website_skips_users_table_in_production(): void
    {
        $originalEnv = app()->environment();
        app()->instance('env', 'production');

        try {
            User::factory()->create(['email' => 'existing@example.com']);

            $zipPath = $this->createZipFileWithUsers();
            $this->importService->importWebsite($zipPath);

            $this->assertDatabaseHas('users', ['email' => 'existing@example.com']);
            $this->assertDatabaseMissing('users', ['email' => 'imported@example.com']);

            unlink($zipPath);
        } finally {
            app()->instance('env', $originalEnv);
        }
    }

    #[Test]
    public function test_import_website_transaction_rollback_on_failure(): void
    {
        Technology::factory()->create(['name' => 'Existing Tech']);

        $zipPath = $this->createZipFileWithInvalidJson();

        try {
            $this->importService->importWebsite($zipPath);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertDatabaseHas('technologies', ['name' => 'Existing Tech']);
        }

        unlink($zipPath);
    }

    private function createValidZipFile(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add database files
        $translationKeysData = [
            [
                'id' => 1,
                'key' => 'technology.imported_tech.description',
            ],
        ];

        $technologiesData = [
            [
                'id' => 1,
                'name' => 'Imported Tech',
                'type' => 'framework',
                'svg_icon' => '<svg></svg>',
                'description_translation_key_id' => 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $picturesData = [
            [
                'id' => 1,
                'filename' => 'imported.jpg',
                'path_original' => '/path/to/imported.jpg',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];

        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));
        $zip->addFromString('database/technologies.json', json_encode($technologiesData));
        $zip->addFromString('database/pictures.json', json_encode($picturesData));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->close();

        return $zipPath;
    }

    private function createValidZipFileWithFiles(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 1,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add empty database files
        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));

        // Add files
        $zip->addFromString('files/uploads/imported-file.txt', 'imported content');

        $zip->close();

        return $zipPath;
    }

    private function createInvalidZipFile(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'invalid_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Create ZIP without required structure
        $zip->addFromString('random-file.txt', 'random content');
        $zip->close();

        return $zipPath;
    }

    private function createZipFileWithNestedFiles(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'nested_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 3,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->addFromString('files/uploads/images/test1.jpg', 'image1');
        $zip->addFromString('files/uploads/images/subfolder/test2.jpg', 'image2');
        $zip->addFromString('files/documents/test.pdf', 'pdf content');

        $zip->close();

        return $zipPath;
    }

    private function createValidZipFileEmptyDatabase(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'empty_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add empty database files
        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/pictures.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->close();

        return $zipPath;
    }

    private function createPartialZipFile(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'partial_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));
        $zip->addFromString('database/technologies.json', json_encode([]));

        $zip->close();

        return $zipPath;
    }

    private function createZipFileWithInvalidJson(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'invalid_json').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add invalid JSON
        $zip->addFromString('database/technologies.json', 'invalid json content');

        $zip->close();

        return $zipPath;
    }

    private function createZipFileWithRelationships(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'relationships_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add related data
        $translationKeysData = [
            [
                'id' => 1,
                'key' => 'technology.laravel.description',
            ],
        ];

        $technologiesData = [
            [
                'id' => 1,
                'name' => 'Laravel',
                'type' => 'framework',
                'svg_icon' => '<svg></svg>',
                'description_translation_key_id' => 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];

        $creationsData = [];

        $pivotData = [];

        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));
        $zip->addFromString('database/technologies.json', json_encode($technologiesData));
        $zip->addFromString('database/creations.json', json_encode($creationsData));
        $zip->addFromString('database/creation_technology.json', json_encode($pivotData));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->close();

        return $zipPath;
    }

    private function createZipFileWithUsers(): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'users_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        $usersData = [
            [
                'id' => 1,
                'name' => 'Imported User',
                'email' => 'imported@example.com',
                'password' => 'hashed_password',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];

        $zip->addFromString('database/users.json', json_encode($usersData));

        $zip->close();

        return $zipPath;
    }
}
