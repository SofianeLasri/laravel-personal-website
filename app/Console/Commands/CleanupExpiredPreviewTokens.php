<?php

namespace App\Console\Commands;

use App\Models\BlogPostPreviewToken;
use Illuminate\Console\Command;

class CleanupExpiredPreviewTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:cleanup-expired-preview-tokens {--days=30 : Number of days after expiration before deletion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired blog post preview tokens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up preview tokens expired for more than {$days} days...");

        // Delete tokens that expired more than X days ago
        $cutoffDate = now()->subDays($days);

        $deletedCount = BlogPostPreviewToken::where('expires_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deletedCount} expired preview token(s).");

        return Command::SUCCESS;
    }
}
