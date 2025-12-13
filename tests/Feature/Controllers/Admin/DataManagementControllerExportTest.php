<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\DataManagementController;
use App\Jobs\ExportWebsiteJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

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
    public function test_export_requires_authentication(): void
    {
        auth()->logout();

        $response = $this->post('/dashboard/data-management/export');

        $response->assertRedirect('/login');
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

        // Fix the time to avoid race condition
        $fixedTime = Carbon::now();
        Carbon::setTestNow($fixedTime);

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
        $response->assertDownload("website-export-{$requestId}-".$fixedTime->format('Y-m-d_H-i-s').'.zip');

        // Reset time
        Carbon::setTestNow(null);
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
