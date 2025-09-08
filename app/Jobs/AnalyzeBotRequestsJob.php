<?php

namespace App\Jobs;

use App\Services\BotDetection\BotDetectionService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use Throwable;

class AnalyzeBotRequestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    private ?int $requestId;

    private int $batchSize;

    private bool $analyzeUnanalyzed;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $requestId  Specific request ID to analyze, or null for batch
     * @param  int  $batchSize  Number of requests to analyze in batch mode
     * @param  bool  $analyzeUnanalyzed  If true, analyze unanalyzed requests. If false, re-analyze old ones
     */
    public function __construct(
        ?int $requestId = null,
        int $batchSize = 100,
        bool $analyzeUnanalyzed = true
    ) {
        $this->requestId = $requestId;
        $this->batchSize = $batchSize;
        $this->analyzeUnanalyzed = $analyzeUnanalyzed;
    }

    /**
     * Execute the job.
     */
    public function handle(BotDetectionService $botDetectionService): void
    {
        try {
            if ($this->requestId) {
                // Analyze specific request
                $request = LoggedRequest::find($this->requestId);
                if ($request) {
                    $result = $botDetectionService->analyzeRequest($request);

                    Log::info('Bot analysis completed for request', [
                        'request_id' => $this->requestId,
                        'is_bot' => $result['is_bot'],
                        'reasons' => $result['reasons'],
                    ]);
                }
            } else {
                // Batch analysis
                if ($this->analyzeUnanalyzed) {
                    $results = $botDetectionService->analyzeUnanalyzedRequests($this->batchSize);

                    $botCount = $results->filter(fn ($r) => $r['analysis']['is_bot'])->count();

                    Log::info('Batch bot analysis completed', [
                        'total_analyzed' => $results->count(),
                        'bots_detected' => $botCount,
                        'type' => 'unanalyzed',
                    ]);
                } else {
                    $results = $botDetectionService->reanalyzeOldRequests(24, $this->batchSize);

                    $botCount = $results->filter(fn ($r) => $r['analysis']['is_bot'])->count();

                    Log::info('Batch bot re-analysis completed', [
                        'total_analyzed' => $results->count(),
                        'bots_detected' => $botCount,
                        'type' => 're-analysis',
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Bot analysis job failed', [
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Bot analysis job permanently failed', [
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
        ]);
    }
}
