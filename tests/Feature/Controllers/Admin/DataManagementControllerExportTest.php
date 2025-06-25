<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\DataManagementController;
use App\Models\Creation;
use App\Models\Picture;
use App\Models\Technology;
use App\Services\WebsiteExportService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;
use ZipArchive;

#[CoversClass(DataManagementController::class)]
class DataManagementControllerExportTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        Storage::fake('public');
        Storage::fake('local');
    }

    #[Test]
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

    #[Test]
    public function test_export_creates_valid_zip_file(): void
    {
        Technology::factory()->create(['name' => 'Laravel']);
        Picture::factory()->create(['filename' => 'test-image.jpg']);
        Creation::factory()->create(['name' => 'Test Project']);

        Storage::disk('public')->put('uploads/test-file.txt', 'test content');

        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $this->assertNotFalse($zip->locateName('export-metadata.json'));
        $this->assertNotFalse($zip->locateName('database/technologies.json'));
        $this->assertNotFalse($zip->locateName('database/pictures.json'));
        $this->assertNotFalse($zip->locateName('database/creations.json'));
        $this->assertNotFalse($zip->locateName('files/uploads/test-file.txt'));

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('tables_exported', $metadata);

        $technologiesData = json_decode($zip->getFromName('database/technologies.json'), true);
        $this->assertIsArray($technologiesData);
        $this->assertCount(1, $technologiesData);
        $this->assertEquals('Laravel', $technologiesData[0]['name']);

        $zip->close();

        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_handles_empty_database(): void
    {
        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $this->assertNotFalse($zip->locateName('export-metadata.json'));

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_includes_all_expected_tables(): void
    {
        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $expectedTables = $exportService->getExportTables();

        foreach ($expectedTables as $table) {
            $this->assertNotFalse(
                $zip->locateName("database/{$table}.json"),
                "Expected table {$table} not found in export"
            );
        }

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->post('/dashboard/data-management/export');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function test_export_preserves_file_structure(): void
    {
        Storage::disk('public')->put('uploads/images/test1.jpg', 'image content 1');
        Storage::disk('public')->put('uploads/images/subfolder/test2.jpg', 'image content 2');
        Storage::disk('public')->put('uploads/documents/test.pdf', 'pdf content');

        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $this->assertNotFalse($zip->locateName('files/uploads/images/test1.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertNotFalse($zip->locateName('files/uploads/documents/test.pdf'));

        $this->assertEquals('image content 1', $zip->getFromName('files/uploads/images/test1.jpg'));
        $this->assertEquals('image content 2', $zip->getFromName('files/uploads/images/subfolder/test2.jpg'));
        $this->assertEquals('pdf content', $zip->getFromName('files/uploads/documents/test.pdf'));

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_contains_valid_json_data(): void
    {
        Technology::factory()->create([
            'name' => 'Test Technology',
            'type' => 'framework',
        ]);

        $exportService = new WebsiteExportService;
        $zipPath = $exportService->exportWebsite();

        $this->assertTrue(Storage::fileExists($zipPath));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::path($zipPath)) === true);

        $technologiesJson = $zip->getFromName('database/technologies.json');
        $this->assertNotFalse($technologiesJson);

        $technologiesData = json_decode($technologiesJson, true);
        $this->assertIsArray($technologiesData);
        $this->assertCount(1, $technologiesData);

        $exportedTechnology = $technologiesData[0];
        $this->assertEquals('Test Technology', $exportedTechnology['name']);
        $this->assertEquals('framework', $exportedTechnology['type']);

        $zip->close();
        Storage::delete($zipPath);
    }

    #[Test]
    public function test_export_cleans_up_old_exports(): void
    {
        $exportService = $this->mock(WebsiteExportService::class);

        $exportService->shouldReceive('exportWebsite')
            ->once()
            ->andReturn('/path/to/export.zip');

        $exportService->shouldReceive('cleanupOldExports')
            ->once()
            ->andReturn(2);

        $this->app->bind('files', function () {
            $files = Mockery::mock(Filesystem::class);
            $files->shouldReceive('exists')->andReturn(true);
            $files->shouldReceive('get')->andReturn('zip content');
            $files->shouldReceive('delete')->andReturn(true);

            return $files;
        });

        $this->post('/dashboard/data-management/export');

        $exportService->shouldHaveReceived('cleanupOldExports');
    }

    #[Test]
    public function test_data_management_page_shows_correct_table_lists(): void
    {
        $response = $this
            ->get('/dashboard/data-management');

        $response->assertInertia(fn ($page) => $page->component('dashboard/DataManagement')
            /*->has('exportTables', fn ($tables) => $tables->where(0, 'users')
                ->where(1, 'translation_keys')
                ->etc()
            )*/
            ->has('importTables', fn ($tables) => $tables->where(0, 'users')
                ->where(1, 'translation_keys')
                ->etc()
            )
        );
    }
}
