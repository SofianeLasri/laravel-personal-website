<?php

namespace App\Console\Commands;

use App\Services\ImageCacheService;
use Illuminate\Console\Command;

class ImageCacheStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:cache-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display image cache statistics';

    /**
     * Execute the console command.
     */
    public function handle(ImageCacheService $imageCacheService): int
    {
        $stats = $imageCacheService->getCacheStats();

        $this->info('Image Cache Statistics');
        $this->line('========================');

        if (! $stats['enabled']) {
            $this->warn('Image cache is disabled');

            return 1;
        }

        $this->line('Status: <info>Enabled</info>');
        $this->line("Total cached images: <info>{$stats['total_keys']}</info>");
        $this->line("Total memory usage: <info>{$stats['total_memory_human']}</info>");
        $this->line("TTL (seconds): <info>{$stats['ttl']}</info>");
        $this->line('Compression: '.($stats['compression_enabled'] ? '<info>Enabled</info>' : '<comment>Disabled</comment>'));
        $this->line("Hash algorithm: <info>{$stats['hash_algorithm']}</info>");

        if ($stats['total_keys'] > 0) {
            $avgMemoryPerKey = $stats['total_memory'] / $stats['total_keys'];
            $this->line('Average memory per image: <info>'.$this->formatBytes($avgMemoryPerKey).'</info>');
        }

        return 0;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
