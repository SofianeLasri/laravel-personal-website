<?php

namespace Tests\Unit\Services;

use App\Models\Creation;
use App\Models\Picture;
use App\Models\Technology;
use App\Services\WebsiteExportService;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(WebsiteExportService::class)]
class WebsiteExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebsiteExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportService = new WebsiteExportService;
        Storage::fake('local');
        Storage::fake('public');
    }

    #[Test]
    public function test_get_export_tables_returns_correct_tables(): void
    {
        $tables = $this->exportService->getExportTables();

        $this->assertIsArray($tables);
        // $this->assertContains('users', $tables);
        $this->assertContains('technologies', $tables);
        $this->assertContains('creations', $tables);
        $this->assertContains('pictures', $tables);

        $usersIndex = array_search('users', $tables);
        $creationTechnologyIndex = array_search('creation_technology', $tables);
        $this->assertLessThan($creationTechnologyIndex, $usersIndex);
    }

    #[Test]
    public function test_export_website_creates_zip_file(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));
        $this->assertStringEndsWith('.zip', $zipPath);
        $this->assertStringContainsString('website-export-', $zipPath);

        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_creates_valid_zip_structure(): void
    {
        Technology::factory()->create(['name' => 'Test Tech']);
        Storage::disk('public')->put('test-file.txt', 'test content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $this->assertNotFalse($zip->locateName('export-metadata.json'));
        $this->assertNotFalse($zip->locateName('database/technologies.json'));
        $this->assertNotFalse($zip->locateName('files/test-file.txt'));

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('database_name', $metadata);
        $this->assertArrayHasKey('tables_exported', $metadata);
        $this->assertArrayHasKey('files_count', $metadata);

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($techData);
        $this->assertCount(1, $techData);
        $this->assertEquals('Test Tech', $techData[0]['name']);

        $fileContent = $zip->getFromName('files/test-file.txt');
        $this->assertEquals('test content', $fileContent);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_handles_empty_database(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $this->assertNotFalse($zip->locateName('database/technologies.json'));

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($techData);
        $this->assertEmpty($techData);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_handles_no_files(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertEquals(0, $metadata['files_count']);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_includes_nested_files(): void
    {
        Storage::disk('public')->put('uploads/images/test1.jpg', 'image1');
        Storage::disk('public')->put('uploads/images/subfolder/test2.jpg', 'image2');
        Storage::disk('public')->put('documents/test.pdf', 'pdf content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $this->assertNotFalse($zip->locateName('files/uploads/images/test1.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertNotFalse($zip->locateName('files/documents/test.pdf'));

        $this->assertEquals('image1', $zip->getFromName('files/uploads/images/test1.jpg'));
        $this->assertEquals('image2', $zip->getFromName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertEquals('pdf content', $zip->getFromName('files/documents/test.pdf'));

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_handles_special_characters(): void
    {
        Technology::factory()->create(['name' => 'Spécial Téch & Ümlauts']);
        Storage::disk('public')->put('spécial-file.txt', 'spécial content');

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertEquals('Spécial Téch & Ümlauts', $techData[0]['name']);

        $fileContent = $zip->getFromName('files/spécial-file.txt');
        $this->assertEquals('spécial content', $fileContent);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_website_includes_all_table_data(): void
    {
        // Create technology with its icon picture (TechnologyFactory creates its own picture)
        $technology = Technology::factory()->create(['name' => 'Laravel']);

        // Create an additional standalone picture
        $testPicture = Picture::factory()->create(['filename' => 'test.jpg']);

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $pictureData = json_decode($zip->getFromName('database/pictures.json'), true);

        $this->assertCount(1, $techData);
        $this->assertGreaterThanOrEqual(2, count($pictureData)); // At least technology icon picture + test picture
        $this->assertEquals('Laravel', $techData[0]['name']);
        $filenames = array_column($pictureData, 'filename');
        $this->assertContains('test.jpg', $filenames);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_cleanup_old_exports_removes_old_files(): void
    {
        $tempDir = Storage::path('temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $oldFile1 = Storage::path('/temp/website-export-old1.zip');
        $oldFile2 = Storage::path('/temp/website-export-old2.zip');
        $recentFile = Storage::path('/website-export-recent.zip');

        touch($oldFile1, time() - (10 * 24 * 60 * 60)); // 10 days old
        touch($oldFile2, time() - (8 * 24 * 60 * 60));  // 8 days old
        touch($recentFile, time() - (3 * 24 * 60 * 60)); // 3 days old

        $deleted = $this->exportService->cleanupOldExports(7);

        $this->assertEquals(2, $deleted);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($recentFile);

        if (file_exists($recentFile)) {
            unlink($recentFile);
        }
    }

    #[Test]
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

    #[Test]
    public function test_export_preserves_database_relationships(): void
    {
        // Create related data
        $tech = Technology::factory()->create(['name' => 'Laravel']);
        $picture = Picture::factory()->create(['filename' => 'test.jpg']);

        // Create a creation that uses both
        $creation = Creation::factory()->create(['name' => 'Test Project']);
        $creation->technologies()->attach($tech);

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        // Verify all related data is exported
        $creationData = json_decode($zip->getFromName('database/creations.json'), true);
        $pivotData = json_decode($zip->getFromName('database/creation_technology.json'), true);

        $this->assertCount(1, $creationData);
        $this->assertCount(1, $pivotData);
        $this->assertEquals($creation->id, $pivotData[0]['creation_id']);
        $this->assertEquals($tech->id, $pivotData[0]['technology_id']);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_metadata_contains_correct_information(): void
    {
        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);

        $this->assertIsString($metadata['export_date']);
        $this->assertIsString($metadata['laravel_version']);
        $this->assertIsString($metadata['database_name']);
        $this->assertIsArray($metadata['tables_exported']);
        $this->assertIsInt($metadata['files_count']);

        // Verify timestamp format (ISO 8601)
        $this->assertNotFalse(DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $metadata['export_date']) ?: DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $metadata['export_date']));

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_handles_large_dataset(): void
    {
        // Create multiple records to test performance
        Technology::factory()->count(100)->create(); // Each tech creates 1 icon picture = 100 pictures
        Picture::factory()->count(50)->create(); // + 50 additional pictures = 150 total

        $zipPath = $this->exportService->exportWebsite();

        $zip = new ZipArchive;
        $zip->open(Storage::path($zipPath));

        $techData = json_decode($zip->getFromName('database/technologies.json'), true);
        $pictureData = json_decode($zip->getFromName('database/pictures.json'), true);

        $this->assertCount(100, $techData);
        $this->assertGreaterThanOrEqual(150, count($pictureData)); // At least 100 icon pictures + 50 additional pictures

        $zip->close();
        Storage::delete($zipPath);
    }
}
