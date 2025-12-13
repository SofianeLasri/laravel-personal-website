<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteExportFileJob;
use App\Jobs\ExportWebsiteJob;
use App\Services\Export\DatabaseExportService;
use App\Services\Export\ExportCleanupService;
use App\Services\Export\ExportMetadataService;
use App\Services\Export\FileExportService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ExportWebsiteJob::class)]
class ExportWebsiteJobTest extends TestCase
{
    use RefreshDatabase;

    private string $cacheKey;

    private string $requestId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheKey = 'export_status_test';
        $this->requestId = 'test-request-123';

        Queue::fake();
        Log::spy();
    }

    protected function tearDown(): void
    {
        // Clean up any test files that may have been created
        Storage::disk('local')->deleteDirectory('temp');
        Storage::deleteDirectory('exports');

        parent::tearDown();
    }

    #[Test]
    public function it_has_correct_job_properties(): void
    {
        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);

        $this->assertEquals(3600, $job->timeout);
        $this->assertEquals(1, $job->tries);
    }

    #[Test]
    public function it_sets_cache_status_to_processing_when_starting(): void
    {
        $this->mockExportServices();

        $processingCacheSet = false;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$processingCacheSet) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'processing') {
                    $processingCacheSet = true;
                    $this->assertEquals($this->requestId, $value['request_id']);
                    $this->assertEquals('Starting export...', $value['progress']);

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')
            ->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        $this->assertTrue($processingCacheSet);
    }

    #[Test]
    public function it_completes_export_successfully(): void
    {
        $this->mockExportServices();

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === 'Website export completed successfully'
                    && $context['request_id'] === $this->requestId;
            })
            ->once();
    }

    #[Test]
    public function it_sets_cache_status_to_completed_on_success(): void
    {
        $this->mockExportServices();

        $completedCacheSet = false;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$completedCacheSet) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'completed') {
                    $completedCacheSet = true;
                    $this->assertEquals($this->requestId, $value['request_id']);
                    $this->assertArrayHasKey('file_path', $value);
                    $this->assertArrayHasKey('file_size', $value);
                    $this->assertArrayHasKey('download_url', $value);
                    $this->assertArrayHasKey('completed_at', $value);

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')
            ->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        $this->assertTrue($completedCacheSet);
    }

    #[Test]
    public function it_dispatches_delete_export_file_job_on_success(): void
    {
        $this->mockExportServices();

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        Queue::assertPushed(DeleteExportFileJob::class);
    }

    #[Test]
    public function it_logs_info_on_successful_export(): void
    {
        $this->mockExportServices();

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === 'Website export completed successfully'
                    && $context['request_id'] === $this->requestId
                    && isset($context['file_path'])
                    && isset($context['file_size']);
            })
            ->once();
    }

    #[Test]
    public function it_handles_exception_during_database_export(): void
    {
        $exception = new Exception('Database export failed');

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow($exception);
            $mock->shouldReceive('getTables')
                ->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('countFiles')
                ->andReturn(0);
        });

        // ExportMetadataService is readonly, create real instance with mocked dependencies
        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class);

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Export failed: Database export failed');

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $job->handle(
            app(DatabaseExportService::class),
            app(FileExportService::class),
            app(ExportMetadataService::class),
            app(ExportCleanupService::class)
        );
    }

    #[Test]
    public function it_handles_exception_during_file_export(): void
    {
        $exception = new Exception('File export failed');

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('export')->once();
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow($exception);
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class);

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Export failed: File export failed');

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $job->handle(
            app(DatabaseExportService::class),
            app(FileExportService::class),
            app(ExportMetadataService::class),
            app(ExportCleanupService::class)
        );
    }

    #[Test]
    public function it_sets_cache_status_to_failed_on_exception(): void
    {
        $exception = new Exception('Export failed');

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow($exception);
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class);

        $failedCacheSet = false;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$failedCacheSet) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'failed') {
                    $failedCacheSet = true;
                    $this->assertEquals($this->requestId, $value['request_id']);
                    $this->assertArrayHasKey('error', $value);
                    $this->assertArrayHasKey('failed_at', $value);

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')
            ->andReturn(['started_at' => now()->toISOString()]);

        try {
            $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
            $job->handle(
                app(DatabaseExportService::class),
                app(FileExportService::class),
                app(ExportMetadataService::class),
                app(ExportCleanupService::class)
            );
        } catch (RuntimeException $e) {
            // Expected exception
        }

        $this->assertTrue($failedCacheSet);
    }

    #[Test]
    public function it_logs_error_on_export_failure(): void
    {
        $exception = new Exception('Export failed');

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) use ($exception) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow($exception);
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class);

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        try {
            $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
            $job->handle(
                app(DatabaseExportService::class),
                app(FileExportService::class),
                app(ExportMetadataService::class),
                app(ExportCleanupService::class)
            );
        } catch (RuntimeException $e) {
            // Expected exception
        }

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context) {
                return $message === 'Website export failed'
                    && $context['request_id'] === $this->requestId
                    && isset($context['error'])
                    && isset($context['trace']);
            })
            ->once();
    }

    #[Test]
    public function it_sets_cache_status_to_failed_in_failed_method(): void
    {
        $exception = new Exception('Job failed');

        $failedCacheSet = false;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$failedCacheSet) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'failed') {
                    $failedCacheSet = true;
                    $this->assertEquals($this->requestId, $value['request_id']);
                    $this->assertEquals('Job failed', $value['error']);
                    $this->assertArrayHasKey('failed_at', $value);

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')
            ->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $job->failed($exception);

        $this->assertTrue($failedCacheSet);
    }

    #[Test]
    public function it_logs_error_in_failed_method(): void
    {
        $exception = new Exception('Job failed');

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $job->failed($exception);

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context) {
                return $message === 'Website export job failed'
                    && $context['request_id'] === $this->requestId
                    && $context['error'] === 'Job failed'
                    && isset($context['trace']);
            })
            ->once();
    }

    #[Test]
    public function it_calls_cleanup_service_after_export(): void
    {
        $cleanupCalled = false;

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('export')->once();
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('export')->once();
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class, function (MockInterface $mock) use (&$cleanupCalled) {
            $mock->shouldReceive('cleanup')
                ->once()
                ->andReturnUsing(function () use (&$cleanupCalled) {
                    $cleanupCalled = true;

                    return 0;
                });
        });

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $job->handle(
            app(DatabaseExportService::class),
            app(FileExportService::class),
            app(ExportMetadataService::class),
            app(ExportCleanupService::class)
        );

        $this->assertTrue($cleanupCalled);
    }

    #[Test]
    public function it_generates_correct_archive_filename(): void
    {
        $this->mockExportServices();

        $capturedFilePath = null;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$capturedFilePath) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'completed') {
                    $capturedFilePath = $value['file_path'];

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        $this->assertNotNull($capturedFilePath);
        $this->assertStringStartsWith('exports/export-'.$this->requestId.'-', $capturedFilePath);
        $this->assertStringEndsWith('.zip', $capturedFilePath);
    }

    #[Test]
    public function it_rethrows_exception_after_handling(): void
    {
        $originalException = new Exception('Original error');

        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) use ($originalException) {
            $mock->shouldReceive('export')
                ->once()
                ->andThrow($originalException);
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class);

        Cache::shouldReceive('put')->andReturnNull();
        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $thrownException = null;
        try {
            $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
            $job->handle(
                app(DatabaseExportService::class),
                app(FileExportService::class),
                app(ExportMetadataService::class),
                app(ExportCleanupService::class)
            );
        } catch (RuntimeException $e) {
            $thrownException = $e;
        }

        $this->assertNotNull($thrownException);
        $this->assertInstanceOf(RuntimeException::class, $thrownException);
        $this->assertStringContainsString('Original error', $thrownException->getMessage());
    }

    #[Test]
    public function it_preserves_started_at_from_cache_on_completion(): void
    {
        $this->mockExportServices();

        $startedAt = now()->subMinutes(5)->toISOString();

        $capturedStartedAt = null;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$capturedStartedAt) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'completed') {
                    $capturedStartedAt = $value['started_at'] ?? null;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')
            ->andReturn(['started_at' => $startedAt]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        $this->assertEquals($startedAt, $capturedStartedAt);
    }

    #[Test]
    public function it_includes_download_url_in_completed_cache(): void
    {
        $this->mockExportServices();

        $capturedDownloadUrl = null;
        Cache::shouldReceive('put')
            ->withArgs(function ($key, $value, $ttl) use (&$capturedDownloadUrl) {
                if ($key === $this->cacheKey && isset($value['status']) && $value['status'] === 'completed') {
                    $capturedDownloadUrl = $value['download_url'] ?? null;

                    return true;
                }

                return true;
            })
            ->andReturnNull();

        Cache::shouldReceive('get')->andReturn(['started_at' => now()->toISOString()]);

        $job = new ExportWebsiteJob($this->cacheKey, $this->requestId);
        $this->dispatchJobWithMockedServices($job);

        $this->assertNotNull($capturedDownloadUrl);
        $this->assertStringContainsString($this->requestId, $capturedDownloadUrl);
    }

    /**
     * Helper method to mock all export services for successful scenarios.
     */
    private function mockExportServices(): void
    {
        $mockDatabaseExport = $this->mock(DatabaseExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('export')->andReturnNull();
            $mock->shouldReceive('getTables')->andReturn([]);
        });

        $mockFileExport = $this->mock(FileExportService::class, function (MockInterface $mock) {
            $mock->shouldReceive('export')->andReturnNull();
            $mock->shouldReceive('countFiles')->andReturn(0);
        });

        // ExportMetadataService is readonly, create real instance with mocked dependencies
        $metadataService = new ExportMetadataService($mockDatabaseExport, $mockFileExport);
        $this->app->instance(ExportMetadataService::class, $metadataService);

        $this->mock(ExportCleanupService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cleanup')->andReturn(0);
        });
    }

    /**
     * Helper method to dispatch job with mocked services.
     */
    private function dispatchJobWithMockedServices(ExportWebsiteJob $job): void
    {
        $job->handle(
            app(DatabaseExportService::class),
            app(FileExportService::class),
            app(ExportMetadataService::class),
            app(ExportCleanupService::class)
        );
    }
}
