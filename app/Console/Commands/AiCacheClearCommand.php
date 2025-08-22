<?php

namespace App\Console\Commands;

use App\Services\AiTranslationCacheService;
use Illuminate\Console\Command;

class AiCacheClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:cache:clear 
                            {--older-than= : Clear cache entries older than X days}
                            {--all : Clear all cache entries}
                            {--stats : Show cache statistics before clearing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear AI translation cache entries';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $cacheService = app(AiTranslationCacheService::class);

        // Show statistics if requested
        if ($this->option('stats')) {
            $this->showStatistics($cacheService);
        }

        // Clear all if requested
        if ($this->option('all')) {
            if (!$this->confirm('Are you sure you want to clear ALL cache entries?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            $deleted = $cacheService->clearAll();
            $this->info("✓ Cleared {$deleted} cache entries.");
            return Command::SUCCESS;
        }

        // Clear expired entries
        $olderThanDays = $this->option('older-than') ?? 30;

        if (!is_numeric($olderThanDays)) {
            $this->error('The --older-than option must be a number.');
            return Command::FAILURE;
        }

        $ttlInSeconds = (int)$olderThanDays * 24 * 60 * 60;
        $deleted = $cacheService->clearExpired($ttlInSeconds);

        $this->info("✓ Cleared {$deleted} cache entries older than {$olderThanDays} days.");

        return Command::SUCCESS;
    }

    /**
     * Display cache statistics
     *
     * @param AiTranslationCacheService $cacheService
     * @return void
     */
    private function showStatistics(AiTranslationCacheService $cacheService): void
    {
        $stats = $cacheService->getStatistics();

        if (empty($stats)) {
            $this->warn('No statistics available.');
            return;
        }

        $this->info('AI Translation Cache Statistics');
        $this->info('================================');
        $this->line("Total Entries: {$stats['total_entries']}");
        $this->line("Total Hits: {$stats['total_hits']}");
        $this->line("Average Hits: " . number_format($stats['average_hits'], 2));

        if (!empty($stats['oldest_entry'])) {
            $this->line("Oldest Entry: {$stats['oldest_entry']}");
        }

        if (!empty($stats['newest_entry'])) {
            $this->line("Newest Entry: {$stats['newest_entry']}");
        }

        if (!empty($stats['providers'])) {
            $this->newLine();
            $this->info('Provider Statistics:');
            $this->table(
                ['Provider', 'Count', 'Total Hits'],
                array_map(function ($provider) {
                    return [
                        $provider['provider'],
                        $provider['count'],
                        $provider['total_hits'],
                    ];
                }, $stats['providers'])
            );
        }

        if (!empty($stats['most_used'])) {
            $this->newLine();
            $this->info('Most Used Cache Entries:');
            $this->table(
                ['Cache Key', 'Provider', 'Hits', 'Created At'],
                array_map(function ($entry) {
                    return [
                        substr($entry['cache_key'], 0, 20) . '...',
                        $entry['provider'],
                        $entry['hits'],
                        $entry['created_at'],
                    ];
                }, $stats['most_used'])
            );
        }

        $this->newLine();
    }
}
