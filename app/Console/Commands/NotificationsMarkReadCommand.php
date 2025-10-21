<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class NotificationsMarkReadCommand extends Command
{
    /**
     * Default period in days when no period is specified
     */
    private const DEFAULT_PERIOD_DAYS = 7;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:mark-read
                            {--older-than=7days : Mark notifications older than this period as read}
                            {--type= : Filter by notification type}
                            {--severity= : Filter by severity (info, warning, error, critical)}
                            {--dry-run : Show what would be marked without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark notifications as read based on age and filters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $olderThan = $this->option('older-than');
        $type = $this->option('type');
        $severity = $this->option('severity');
        $dryRun = $this->option('dry-run');

        // Parse the older-than period
        $cutoffDate = $this->parsePeriod($olderThan);

        $this->info("Processing notifications older than {$cutoffDate->format('Y-m-d H:i:s')}");

        // Build query
        $query = Notification::query()
            ->where('is_read', false)
            ->where('created_at', '<=', $cutoffDate);

        if ($type) {
            $query->where('type', $type);
            $this->info("Filtering by type: {$type}");
        }

        if ($severity) {
            $query->where('severity', $severity);
            $this->info("Filtering by severity: {$severity}");
        }

        // Get count before update
        $count = $query->count();

        if ($count === 0) {
            $this->info('No unread notifications found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$count} unread notification(s) to mark as read.");

        // Show breakdown by type and severity
        $breakdown = clone $query;
        /** @var \Illuminate\Support\Collection<int, object{type: string, severity: string, count: int}> $typeBreakdown */
        $typeBreakdown = $breakdown->selectRaw('type, severity, count(*) as count')
            ->groupBy('type', 'severity')
            ->get();

        if ($typeBreakdown->isNotEmpty()) {
            $this->newLine();
            $this->info('Breakdown by Type and Severity:');
            $this->table(
                ['Type', 'Severity', 'Count'],
                $typeBreakdown->map(function ($item) {
                    return [
                        $item->type,
                        ucfirst($item->severity),
                        $item->count,
                    ];
                })
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN: No changes were made.');
            $this->info("Would have marked {$count} notification(s) as read.");

            // Show sample of notifications that would be marked
            $samples = $query->limit(5)->get();
            if ($samples->isNotEmpty()) {
                $this->newLine();
                $this->info('Sample notifications that would be marked:');
                $this->table(
                    ['ID', 'Type', 'Severity', 'Title', 'Created At'],
                    $samples->map(function ($notification) {
                        return [
                            $notification->id,
                            $notification->type,
                            ucfirst($notification->severity),
                            substr($notification->title, 0, 40).(strlen($notification->title) > 40 ? '...' : ''),
                            $notification->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
                        ];
                    })
                );
            }

            return Command::SUCCESS;
        }

        // Confirm before marking
        if (! $this->confirm("Are you sure you want to mark {$count} notification(s) as read?")) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        // Mark as read
        $updated = $query->update([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ]);

        $this->success("Successfully marked {$updated} notification(s) as read.");

        // Show summary of remaining unread notifications
        $remainingUnread = Notification::where('is_read', false)->count();
        if ($remainingUnread > 0) {
            $this->newLine();
            $this->info("Remaining unread notifications: {$remainingUnread}");

            // Show breakdown of remaining
            /** @var \Illuminate\Support\Collection<int, object{severity: string, count: int}> $remainingBreakdown */
            $remainingBreakdown = Notification::where('is_read', false)
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->get();

            if ($remainingBreakdown->isNotEmpty()) {
                $this->table(
                    ['Severity', 'Count'],
                    $remainingBreakdown->map(function ($item) {
                        return [
                            ucfirst($item->severity),
                            $item->count,
                        ];
                    })
                );
            }
        } else {
            $this->newLine();
            $this->success('All notifications have been marked as read!');
        }

        return Command::SUCCESS;
    }

    /**
     * Parse the period option into a Carbon date
     */
    private function parsePeriod(?string $period): Carbon
    {
        // Default to 7 days if period is null
        if ($period === null) {
            return Carbon::now()->subDays(self::DEFAULT_PERIOD_DAYS);
        }

        // Handle common formats
        if (preg_match('/^(\d+)(hours?|days?|weeks?|months?)$/', $period, $matches)) {
            $value = (int) $matches[1];
            $unit = rtrim($matches[2], 's'); // Remove plural 's'

            return Carbon::now()->sub($unit, $value);
        }

        // Try to parse as a date
        try {
            return Carbon::parse($period);
        } catch (Exception $e) {
            // Default to 7 days
            $this->warn("Could not parse period '{$period}', defaulting to 7 days");

            return Carbon::now()->subDays(7);
        }
    }

    /**
     * Write a success message to the console.
     */
    private function success(string $message): void
    {
        $this->line("<fg=green>✓</> {$message}");
    }
}
