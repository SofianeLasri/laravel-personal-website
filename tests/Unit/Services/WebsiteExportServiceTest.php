<?php

namespace Tests\Unit\Services;

use App\Models\Picture;
use App\Models\Technology;
use App\Services\WebsiteExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class WebsiteExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebsiteExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportService = new WebsiteExportService;
        Storage::fake('public');
    }

    public function test_get_export_tables_returns_correct_tables(): void
    {
        $tables = $this->exportService->getExportTables();

        $this->assertIsArray($tables);
        $this->assertContains('users', $tables);
        $this->assertContains('technologies', $tables);
        $this->assertContains('creations', $tables);
        $this->assertContains('pictures', $tables);

        // Verify order - users should come before pivot tables
        $usersIndex = array_search('users', $tables);
        $creationTechnologyIndex = array_search('creation_technology', $tables);
        $this->assertLessThan($creationTechnologyIndex, $usersIndex);
    }

    public function test_export_website_creates_zip_file(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $this->assertFileExists($zipPath);
        $this->assertStringEndsWith('.zip', $zipPath);
        $this->assertStringContainsString('website-export-', $zipPath);

        // Clean up
        unlink($zipPath);
    }

    public function test_export_website_creates_valid_zip_structure(): void
    {
        // Create test data
        Technology::factory()->create(['name' => 'Test Tech']);
        Storage::disk('public')->put('test-file.txt', 'test content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        // Check required files exist
        $this->assertNotFalse($zip->locateName('export-metadata.json'));
        $this->assertNotFalse($zip->locateName('database/technologies.json'));
        $this->assertNotFalse($zip->locateName('files/test-file.txt'));

        // Verify metadata structure
        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('database_name', $metadata);
        $this->assertArrayHasKey('tables_exported', $metadata);
        $this->assertArrayHasKey('files_count', $metadata);

        // Verify database export
        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($techData);
        $this->assertCount(1, $techData);
        $this->assertEquals('Test Tech', $techData[0]['name']);

        // Verify file export
        $fileContent = $zip->getFromName('files/test-file.txt');
        $this->assertEquals('test content', $fileContent);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_website_handles_empty_database(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        // Should still create database files even if empty
        $this->assertNotFalse($zip->locateName('database/technologies.json'));

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($techData);
        $this->assertEmpty($techData);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_website_handles_no_files(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        // Should still create metadata even with no files
        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertEquals(0, $metadata['files_count']);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_website_includes_nested_files(): void
    {
        Storage::disk('public')->put('uploads/images/test1.jpg', 'image1');
        Storage::disk('public')->put('uploads/images/subfolder/test2.jpg', 'image2');
        Storage::disk('public')->put('documents/test.pdf', 'pdf content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('files/uploads/images/test1.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertNotFalse($zip->locateName('files/documents/test.pdf'));

        $this->assertEquals('image1', $zip->getFromName('files/uploads/images/test1.jpg'));
        $this->assertEquals('image2', $zip->getFromName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertEquals('pdf content', $zip->getFromName('files/documents/test.pdf'));

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_website_handles_special_characters(): void
    {
        Technology::factory()->create(['name' => 'Spécial Téch & Ümlauts']);
        Storage::disk('public')->put('spécial-file.txt', 'spécial content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertEquals('Spécial Téch & Ümlauts', $techData[0]['name']);

        $fileContent = $zip->getFromName('files/spécial-file.txt');
        $this->assertEquals('spécial content', $fileContent);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_website_includes_all_table_data(): void
    {
        // Create test data for multiple tables
        $tech = Technology::factory()->create(['name' => 'Laravel']);
        $picture = Picture::factory()->create(['filename' => 'test.jpg']);

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        // Verify both tables are exported
        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $pictureData = json_decode($zip->getFromName('database/pictures.json'), true);

        $this->assertCount(1, $techData);
        $this->assertCount(1, $pictureData);
        $this->assertEquals('Laravel', $techData[0]['name']);
        $this->assertEquals('test.jpg', $pictureData[0]['filename']);

        $zip->close();
        unlink($zipPath);
    }

    public function test_cleanup_old_exports_removes_old_files(): void
    {
        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Create old export files
        $oldFile1 = $tempDir.'/website-export-old1.zip';
        $oldFile2 = $tempDir.'/website-export-old2.zip';
        $recentFile = $tempDir.'/website-export-recent.zip';

        touch($oldFile1, time() - (10 * 24 * 60 * 60)); // 10 days old
        touch($oldFile2, time() - (8 * 24 * 60 * 60));  // 8 days old
        touch($recentFile, time() - (3 * 24 * 60 * 60)); // 3 days old

        $deleted = $this->exportService->cleanupOldExports(7);

        $this->assertEquals(2, $deleted);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($recentFile);

        // Clean up
        if (file_exists($recentFile)) {
            unlink($recentFile);
        }
    }

    public function test_cleanup_old_exports_handles_nonexistent_directory(): void
    {
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            // Remove all files first
            $files = glob($tempDir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);
        }

        $deleted = $this->exportService->cleanupOldExports();

        $this->assertEquals(0, $deleted);
    }

    public function test_export_preserves_database_relationships(): void
    {
        // Create related data
        $tech = Technology::factory()->create(['name' => 'Laravel']);
        $picture = Picture::factory()->create(['filename' => 'test.jpg']);

        // Create a creation that uses both
        $creation = \App\Models\Creation::factory()->create(['name' => 'Test Project']);
        $creation->technologies()->attach($tech);

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        // Verify all related data is exported
        $creationData = json_decode($zip->getFromName('database/creations.json'), true);
        $pivotData = json_decode($zip->getFromName('database/creation_technology.json'), true);

        $this->assertCount(1, $creationData);
        $this->assertCount(1, $pivotData);
        $this->assertEquals($creation->id, $pivotData[0]['creation_id']);
        $this->assertEquals($tech->id, $pivotData[0]['technology_id']);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_metadata_contains_correct_information(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);

        $this->assertIsString($metadata['export_date']);
        $this->assertIsString($metadata['laravel_version']);
        $this->assertIsString($metadata['database_name']);
        $this->assertIsArray($metadata['tables_exported']);
        $this->assertIsInt($metadata['files_count']);

        // Verify timestamp format (ISO 8601)
        $this->assertNotFalse(\DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $metadata['export_date']) ?: \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $metadata['export_date']));

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_handles_large_dataset(): void
    {
        // Create multiple records to test performance
        Technology::factory()->count(100)->create();
        Picture::factory()->count(50)->create();

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open($zipPath);

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $pictureData = json_decode($zip->getFromName('database/pictures.json'), true);

        $this->assertCount(100, $techData);
        $this->assertCount(50, $pictureData);

        $zip->close();
        unlink($zipPath);
    }
}
