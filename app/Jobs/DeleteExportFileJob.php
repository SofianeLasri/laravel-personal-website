<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job to automatically delete export files after 15 minutes.
 */
class DeleteExportFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60; // 1 minute timeout

    public int $tries = 1; // Single attempt

    public function __construct(
        private readonly string $filePath,
        private readonly string $cacheKey
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
                Log::info('Export file deleted automatically', [
                    'file_path' => $this->filePath,
                    'cache_key' => $this->cacheKey,
                ]);
            }

            Cache::forget($this->cacheKey);
        } catch (Exception $e) {
            Log::error('Failed to delete export file', [
                'file_path' => $this->filePath,
                'cache_key' => $this->cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
