<?php

namespace App\Console\Commands;

use App\Services\ImageCacheService;
use Illuminate\Console\Command;

class ClearImageCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:cache-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all cached image optimizations';

    /**
     * Execute the console command.
     */
    public function handle(ImageCacheService $imageCacheService): int
    {
        if (! config('images.cache.enabled', false)) {
            $this->warn('Image cache is disabled');

            return 1;
        }

        $this->info('Clearing image cache...');

        $deletedCount = $imageCacheService->clearCache();

        if ($deletedCount > 0) {
            $this->info("Successfully cleared {$deletedCount} cached optimizations");
        } else {
            $this->info('No cached optimizations found');
        }

        return 0;
    }
}
