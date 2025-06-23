<?php

namespace App\Jobs;

use App\Services\WebsiteExportService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Throwable;

/**
 * Background job for website data export to prevent controller timeouts.
 */
class ExportWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout

    public int $tries = 1; // Single attempt

    public function __construct(
        private readonly string $cacheKey,
        private readonly string $requestId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebsiteExportService $exportService): void
    {
        try {
            Cache::put($this->cacheKey, [
                'status' => 'processing',
                'request_id' => $this->requestId,
                'started_at' => now()->toISOString(),
                'progress' => 'Starting export...',
            ], now()->addMinutes(20));

            $zipPath = $exportService->exportWebsite();
            $exportService->cleanupOldExports();

            $archiveFileName = "export-{$this->requestId}-".now()->format('Y-m-d_H-i-s').'.zip';
            $archivePath = "exports/{$archiveFileName}";

            Storage::makeDirectory('exports');

            if (! Storage::move($zipPath, $archivePath)) {
                throw new RuntimeException('Failed to move export file');
            }

            $fullArchivePath = Storage::path($archivePath);

            Cache::put($this->cacheKey, [
                'status' => 'completed',
                'request_id' => $this->requestId,
                'started_at' => Cache::get($this->cacheKey)['started_at'] ?? now()->toISOString(),
                'completed_at' => now()->toISOString(),
                'file_path' => $archivePath,
                'file_size' => filesize($fullArchivePath),
                'download_url' => route('dashboard.data-management.download', ['requestId' => $this->requestId]),
            ], now()->addMinutes(15));

            DeleteExportFileJob::dispatch($archivePath, $this->cacheKey)
                ->delay(now()->addMinutes(15));

            Log::info('Website export completed successfully', [
                'request_id' => $this->requestId,
                'file_path' => $archivePath,
                'file_size' => filesize($fullArchivePath),
            ]);

        } catch (Exception|FilesystemException $e) {
            Cache::put($this->cacheKey, [
                'status' => 'failed',
                'request_id' => $this->requestId,
                'started_at' => Cache::get($this->cacheKey)['started_at'] ?? now()->toISOString(),
                'failed_at' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], now()->addMinutes(5));

            Log::error('Website export failed', [
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Cache::put($this->cacheKey, [
            'status' => 'failed',
            'request_id' => $this->requestId,
            'started_at' => Cache::get($this->cacheKey)['started_at'] ?? now()->toISOString(),
            'failed_at' => now()->toISOString(),
            'error' => $exception->getMessage(),
        ], now()->addMinutes(5));

        Log::error('Website export job failed', [
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
