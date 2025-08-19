<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeBotRequestsJob;
use App\Services\BotDetection\BotDetectionService;
use Exception;
use Illuminate\Console\Command;

class AnalyzeBotRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:analyze 
                            {--batch-size=100 : Number of requests to analyze per batch}
                            {--re-analyze : Re-analyze old requests instead of unanalyzed ones}
                            {--request-id= : Analyze a specific request ID}
                            {--queue : Dispatch job to queue instead of running synchronously}
                            {--include-authenticated : Include authenticated users in the analysis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze logged requests for bot behavior';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $reAnalyze = $this->option('re-analyze');
        $requestId = $this->option('request-id');
        $useQueue = $this->option('queue');

        if ($requestId) {
            $this->info("Analyzing specific request ID: {$requestId}");
            $job = new AnalyzeBotRequestsJob((int) $requestId);
        } else {
            $type = $reAnalyze ? 're-analyzing old' : 'analyzing unanalyzed';
            $this->info("Starting batch analysis: {$type} requests (batch size: {$batchSize})");
            $job = new AnalyzeBotRequestsJob(null, $batchSize, ! $reAnalyze);
        }

        if ($useQueue) {
            dispatch($job);
            $this->info('Job dispatched to queue successfully.');
        } else {
            $this->info('Running analysis synchronously...');

            try {
                $job->handle(app(BotDetectionService::class));
                $this->info('Analysis completed successfully.');
            } catch (Exception $e) {
                $this->error('Analysis failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
