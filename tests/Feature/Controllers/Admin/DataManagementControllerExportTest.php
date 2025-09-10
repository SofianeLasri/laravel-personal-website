<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\DataManagementController;
use App\Jobs\ExportWebsiteJob;
use App\Models\Creation;
use App\Models\Picture;
use App\Models\Technology;
use App\Services\WebsiteExportService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
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

        $response->assertStatus(ResponseAlias::HTTP_OK);
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
            ->has('importTables', fn ($tables) => $tables->where(0, 'translation_keys')
                ->where(1, 'pictures')
                ->etc()
            )
        );
    }

    #[Test]
    public function test_export_endpoint_initiates_background_job(): void
    {
        // Mock the job to prevent actual file operations
        Queue::fake();

        $response = $this->postJson('/dashboard/data-management/export');

        $response->assertStatus(ResponseAlias::HTTP_ACCEPTED);
        $response->assertJsonStructure([
            'status',
            'message',
            'request_id',
            'status_url',
        ]);
        $response->assertJson([
            'status' => 'accepted',
            'message' => 'Export request accepted. Processing in background.',
        ]);

        $requestId = $response->json('request_id');
        $this->assertNotEmpty($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $requestId
        );

        // Verify job was dispatched
        Queue::assertPushed(ExportWebsiteJob::class);
    }

    #[Test]
    public function test_export_prevents_concurrent_exports(): void
    {
        // Mock the job to prevent actual file operations
        Queue::fake();

        // First export request
        $response1 = $this->postJson('/dashboard/data-management/export');
        $response1->assertStatus(ResponseAlias::HTTP_ACCEPTED);
        $requestId1 = $response1->json('request_id');

        // Second export request should be rejected
        $response2 = $this->postJson('/dashboard/data-management/export');
        $response2->assertStatus(ResponseAlias::HTTP_CONFLICT);
        $response2->assertJson([
            'status' => 'already_in_progress',
            'message' => 'An export is already in progress. Please wait for it to complete.',
            'request_id' => $requestId1,
        ]);
        $response2->assertJsonStructure([
            'status',
            'message',
            'request_id',
            'started_at',
        ]);
    }

    #[Test]
    public function test_export_status_returns_not_found_for_invalid_request(): void
    {
        $invalidRequestId = 'invalid-request-id';

        $response = $this->getJson("/dashboard/data-management/export/{$invalidRequestId}/status");

        $response->assertStatus(ResponseAlias::HTTP_NOT_FOUND);
        $response->assertJson([
            'status' => 'not_found',
            'message' => 'Export request not found or expired.',
        ]);
    }

    #[Test]
    public function test_export_status_returns_current_status(): void
    {
        // Mock the job to prevent actual file operations
        Queue::fake();

        // Initiate export
        $exportResponse = $this->postJson('/dashboard/data-management/export');
        $requestId = $exportResponse->json('request_id');

        // Check status
        $response = $this->getJson("/dashboard/data-management/export/{$requestId}/status");

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'status',
            'request_id',
        ]);
        $response->assertJson([
            'request_id' => $requestId,
        ]);
    }

    #[Test]
    public function test_download_export_returns_not_ready_if_not_completed(): void
    {
        // Mock the job to prevent actual file operations
        Queue::fake();

        // Initiate export
        $exportResponse = $this->postJson('/dashboard/data-management/export');
        $requestId = $exportResponse->json('request_id');

        // Try to download immediately
        $response = $this->get("/dashboard/data-management/export/{$requestId}/download");

        $response->assertStatus(ResponseAlias::HTTP_NOT_FOUND);
        $response->assertJson([
            'status' => 'not_ready',
            'message' => 'Export not ready for download.',
        ]);
    }

    #[Test]
    public function test_download_export_returns_file_when_completed(): void
    {
        $requestId = 'test-request-id';
        $cacheKey = "data_export_status_{$requestId}";
        $filePath = 'exports/test-export.zip';

        // Create a test file
        Storage::put($filePath, 'test zip content');

        // Set cache to indicate completed export
        Cache::put($cacheKey, [
            'status' => 'completed',
            'request_id' => $requestId,
            'file_path' => $filePath,
            'completed_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        $response = $this->get("/dashboard/data-management/export/{$requestId}/download");

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/zip');
        $response->assertDownload("website-export-{$requestId}-".now()->format('Y-m-d_H-i-s').'.zip');
    }

    #[Test]
    public function test_download_export_handles_missing_file(): void
    {
        $requestId = 'test-request-id';
        $cacheKey = "data_export_status_{$requestId}";

        // Set cache to indicate completed export but without file
        Cache::put($cacheKey, [
            'status' => 'completed',
            'request_id' => $requestId,
            'file_path' => 'exports/missing-file.zip',
            'completed_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        $response = $this->get("/dashboard/data-management/export/{$requestId}/download");

        $response->assertStatus(ResponseAlias::HTTP_NOT_FOUND);
        $response->assertJson([
            'status' => 'file_not_found',
            'message' => 'Export file not found or expired.',
        ]);
    }

    #[Test]
    public function test_export_status_clears_lock_on_completion(): void
    {
        $requestId = 'test-request-id';
        $cacheKey = "data_export_status_{$requestId}";
        $lockKey = 'data_export_lock';

        // Set lock
        Cache::put($lockKey, [
            'request_id' => $requestId,
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        // Set status as completed
        Cache::put($cacheKey, [
            'status' => 'completed',
            'request_id' => $requestId,
            'file_path' => 'exports/test.zip',
            'completed_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        // Check status (should clear lock)
        $response = $this->getJson("/dashboard/data-management/export/{$requestId}/status");
        $response->assertSuccessful();

        // Verify lock is cleared
        $this->assertFalse(Cache::has($lockKey));
    }

    #[Test]
    public function test_export_status_clears_lock_on_failure(): void
    {
        $requestId = 'test-request-id';
        $cacheKey = "data_export_status_{$requestId}";
        $lockKey = 'data_export_lock';

        // Set lock
        Cache::put($lockKey, [
            'request_id' => $requestId,
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        // Set status as failed
        Cache::put($cacheKey, [
            'status' => 'failed',
            'request_id' => $requestId,
            'error' => 'Export failed due to error',
            'failed_at' => now()->toISOString(),
        ], now()->addMinutes(20));

        // Check status (should clear lock)
        $response = $this->getJson("/dashboard/data-management/export/{$requestId}/status");
        $response->assertSuccessful();

        // Verify lock is cleared
        $this->assertFalse(Cache::has($lockKey));
    }

    #[Test]
    public function test_export_endpoints_require_authentication(): void
    {
        auth()->logout();

        // Test export endpoint
        $response = $this->post('/dashboard/data-management/export');
        $response->assertRedirect('/login');

        // Test export status endpoint
        $response = $this->get('/dashboard/data-management/export/test-id/status');
        $response->assertRedirect('/login');

        // Test download endpoint
        $response = $this->get('/dashboard/data-management/export/test-id/download');
        $response->assertRedirect('/login');
    }
}
