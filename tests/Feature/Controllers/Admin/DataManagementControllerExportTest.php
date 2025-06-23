<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\Creation;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\User;
use App\Services\WebsiteExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;
use ZipArchive;

class DataManagementControllerExportTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        Storage::fake('public');
    }

    public function test_data_management_index_page_loads_successfully(): void
    {
        $response = $this
            ->get('/dashboard/data-management');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertInertia(fn ($page) => $page->component('dashboard/DataManagement')
            ->has('exportTables')
            ->has('importTables')
        );
    }

    public function test_export_creates_valid_zip_file(): void
    {
        // Create test data
        $technology = Technology::factory()->create(['name' => 'Laravel']);
        $picture = Picture::factory()->create(['filename' => 'test-image.jpg']);
        $creation = Creation::factory()->create(['name' => 'Test Project']);

        // Create test file in storage
        Storage::disk('public')->put('uploads/test-file.txt', 'test content');

        // Test the export service directly instead of through HTTP
        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        // Check for expected files
        $this->assertNotFalse($zip->locateName('export-metadata.json'));
        $this->assertNotFalse($zip->locateName('database/technologies.json'));
        $this->assertNotFalse($zip->locateName('database/pictures.json'));
        $this->assertNotFalse($zip->locateName('database/creations.json'));
        $this->assertNotFalse($zip->locateName('files/uploads/test-file.txt'));

        // Verify metadata content
        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('tables_exported', $metadata);

        // Verify database content
        $technologiesData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($technologiesData);
        $this->assertCount(1, $technologiesData);
        $this->assertEquals('Laravel', $technologiesData[0]['name']);

        $zip->close();

        // Clean up
        unlink($zipPath);
    }

    public function test_export_handles_empty_database(): void
    {
        // Test the export service directly with empty database
        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        // Should still have metadata and empty database files
        $this->assertNotFalse($zip->locateName('export-metadata.json'));

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_includes_all_expected_tables(): void
    {
        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        $expectedTables = $exportService->getExportTables();

        foreach ($expectedTables as $table) {
            $this->assertNotFalse(
                $zip->locateName("database/{$table}.json"),
                "Expected table {$table} not found in export"
            );
        }

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_requires_authentication(): void
    {
        // Logout the user that was logged in during setUp
        auth()->logout();

        $response = $this->post('/dashboard/data-management/export');

        $response->assertRedirect('/login');
    }

    public function test_export_preserves_file_structure(): void
    {
        // Create nested file structure
        Storage::disk('public')->put('uploads/images/test1.jpg', 'image content 1');
        Storage::disk('public')->put('uploads/images/subfolder/test2.jpg', 'image content 2');
        Storage::disk('public')->put('uploads/documents/test.pdf', 'pdf content');

        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        // Verify file structure is preserved
        $this->assertNotFalse($zip->locateName('files/uploads/images/test1.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/documents/test.pdf'));

        // Verify file content
        $this->assertEquals('image content 1', $zip->getFromName('files/uploads/images/test1.jpg'));
        $this->assertEquals('image content 2', $zip->getFromName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertEquals('pdf content', $zip->getFromName('files/uploads/documents/test.pdf'));

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_contains_valid_json_data(): void
    {
        $technology = Technology::factory()->create([
            'name' => 'Test Technology',
            'type' => 'framework',
        ]);

        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        $technologiesJson = $zip->getFromName('database/technologies.json');
        $this->assertNotFalse($technologiesJson);

        $technologiesData = json_decode($technologiesJson, true);
        $this->assertIsArray($technologiesData);
        $this->assertCount(1, $technologiesData);

        $exportedTechnology = $technologiesData[0];
        $this->assertEquals('Test Technology', $exportedTechnology['name']);
        $this->assertEquals('framework', $exportedTechnology['type']);

        $zip->close();
        unlink($zipPath);
    }

    public function test_export_handles_service_failure_gracefully(): void
    {
        // Mock the export service to throw an exception
        $this->mock(WebsiteExportService::class, function ($mock) {
            $mock->shouldReceive('exportWebsite')
                ->once()
                ->andThrow(new \RuntimeException('Export failed'));
            $mock->shouldReceive('cleanupOldExports')->never();
        });

        $response = $this
            ->post('/dashboard/data-management/export');

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJson([
            'message' => 'Export failed: Export failed',
        ]);
    }

    public function test_export_cleans_up_old_exports(): void
    {
        $exportService = $this->mock(WebsiteExportService::class);

        $exportService->shouldReceive('exportWebsite')
            ->once()
            ->andReturn('/path/to/export.zip');

        $exportService->shouldReceive('cleanupOldExports')
            ->once()
            ->andReturn(2); // Simulate cleaning up 2 old files

        // Mock file_exists and other file operations
        $this->app->bind('files', function () {
            $files = \Mockery::mock(\Illuminate\Filesystem\Filesystem::class);
            $files->shouldReceive('exists')->andReturn(true);
            $files->shouldReceive('get')->andReturn('zip content');
            $files->shouldReceive('delete')->andReturn(true);

            return $files;
        });

        $response = $this
            ->post('/dashboard/data-management/export');

        // The cleanup should be called even on successful export
        $exportService->shouldHaveReceived('cleanupOldExports');
    }

    public function test_data_management_page_shows_correct_table_lists(): void
    {
        $response = $this
            ->get('/dashboard/data-management');

        $response->assertInertia(fn ($page) => $page->component('dashboard/DataManagement')
            ->has('exportTables', fn ($tables) => $tables->where(0, 'users')
                ->where(1, 'translation_keys')
                ->etc()
            )
            ->has('importTables', fn ($tables) => $tables->where(0, 'users')
                ->where(1, 'translation_keys')
                ->etc()
            )
        );
    }
}
