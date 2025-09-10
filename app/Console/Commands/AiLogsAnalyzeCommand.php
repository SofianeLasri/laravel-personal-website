<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiLogsAnalyzeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:logs:analyze 
                            {--period=7days : The period to analyze (e.g., 7days, 1month, 24hours)}
                            {--provider= : Filter by specific provider}
                            {--status= : Filter by status (success, error, timeout, fallback)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze AI API request logs and display statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period');
        $provider = $this->option('provider');
        $status = $this->option('status');

        // Parse period
        $startDate = $this->parsePeriod($period);

        $this->info("Analyzing AI API logs from {$startDate->format('Y-m-d H:i:s')} to now");

        // Build query
        $query = ApiRequestLog::query()
            ->where('created_at', '>=', $startDate);

        if ($provider) {
            $query->where('provider', $provider);
            $this->info("Filtering by provider: {$provider}");
        }

        if ($status) {
            $query->where('status', $status);
            $this->info("Filtering by status: {$status}");
        }

        // Get statistics
        $totalRequests = $query->count();

        if ($totalRequests === 0) {
            $this->warn('No requests found for the specified period.');

            return Command::SUCCESS;
        }

        // Status breakdown
        $statusBreakdown = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // Provider breakdown
        $providerBreakdown = $query->select('provider', DB::raw('count(*) as count'))
            ->groupBy('provider')
            ->get();

        // Performance metrics
        $performanceMetrics = $query->select(
            DB::raw('AVG(response_time) as avg_response_time'),
            DB::raw('MIN(response_time) as min_response_time'),
            DB::raw('MAX(response_time) as max_response_time'),
            DB::raw('SUM(input_tokens) as total_input_tokens'),
            DB::raw('SUM(output_tokens) as total_output_tokens'),
            DB::raw('SUM(estimated_cost) as total_cost')
        )->first();

        // Cached vs non-cached
        $cachedCount = $query->where('cached', true)->count();
        $nonCachedCount = $totalRequests - $cachedCount;

        // Display results
        $this->newLine();
        $this->info('📊 API Request Statistics');
        $this->info('─────────────────────────');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', number_format($totalRequests)],
                ['Cached Responses', number_format($cachedCount).' ('.round(($cachedCount / $totalRequests) * 100, 1).'%)'],
                ['API Calls', number_format($nonCachedCount).' ('.round(($nonCachedCount / $totalRequests) * 100, 1).'%)'],
            ]
        );

        $this->newLine();
        $this->info('📈 Status Breakdown');
        $this->table(
            ['Status', 'Count', 'Percentage'],
            $statusBreakdown->map(function ($item) use ($totalRequests) {
                return [
                    ucfirst($item->status),
                    number_format($item->count),
                    round(($item->count / $totalRequests) * 100, 1).'%',
                ];
            })
        );

        $this->newLine();
        $this->info('🤖 Provider Breakdown');
        $this->table(
            ['Provider', 'Count', 'Percentage'],
            $providerBreakdown->map(function ($item) use ($totalRequests) {
                return [
                    ucfirst($item->provider),
                    number_format($item->count),
                    round(($item->count / $totalRequests) * 100, 1).'%',
                ];
            })
        );

        $this->newLine();
        $this->info('⚡ Performance Metrics');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Average Response Time', round($performanceMetrics->avg_response_time ?? 0, 3).' seconds'],
                ['Min Response Time', round($performanceMetrics->min_response_time ?? 0, 3).' seconds'],
                ['Max Response Time', round($performanceMetrics->max_response_time ?? 0, 3).' seconds'],
            ]
        );

        $this->newLine();
        $this->info('💰 Token Usage & Costs');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Input Tokens', number_format($performanceMetrics->total_input_tokens ?? 0)],
                ['Total Output Tokens', number_format($performanceMetrics->total_output_tokens ?? 0)],
                ['Total Tokens', number_format(($performanceMetrics->total_input_tokens ?? 0) + ($performanceMetrics->total_output_tokens ?? 0))],
                ['Estimated Total Cost', '$'.number_format($performanceMetrics->total_cost ?? 0, 4)],
                ['Average Cost per Request', '$'.number_format(($performanceMetrics->total_cost ?? 0) / $totalRequests, 6)],
            ]
        );

        // Get top errors
        $errors = $query->where('status', 'error')
            ->select('error_message', DB::raw('count(*) as count'))
            ->whereNotNull('error_message')
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        if ($errors->isNotEmpty()) {
            $this->newLine();
            $this->warn('⚠️ Top Errors');
            $this->table(
                ['Error Message', 'Count'],
                $errors->map(function ($item) {
                    return [
                        substr($item->error_message, 0, 80).(strlen($item->error_message) > 80 ? '...' : ''),
                        number_format($item->count),
                    ];
                })
            );
        }

        // Daily breakdown
        $dailyBreakdown = $query->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count'),
            DB::raw('SUM(estimated_cost) as daily_cost')
        )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get();

        if ($dailyBreakdown->isNotEmpty()) {
            $this->newLine();
            $this->info('📅 Daily Breakdown (Last 7 Days)');
            $this->table(
                ['Date', 'Requests', 'Cost'],
                $dailyBreakdown->map(function ($item) {
                    return [
                        $item->date,
                        number_format($item->count),
                        '$'.number_format($item->daily_cost ?? 0, 4),
                    ];
                })
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Parse the period option into a Carbon date
     */
    private function parsePeriod(string $period): Carbon
    {
        // Handle common formats
        if (preg_match('/^(\d+)(hours?|days?|weeks?|months?)$/', $period, $matches)) {
            $value = (int) $matches[1];
            $unit = rtrim($matches[2], 's'); // Remove plural 's'

            return Carbon::now()->sub($unit, $value);
        }

        // Try to parse as a date
        try {
            return Carbon::parse($period);
        } catch (\Exception $e) {
            // Default to 7 days
            $this->warn("Could not parse period '{$period}', defaulting to 7 days");

            return Carbon::now()->subDays(7);
        }
    }
}
