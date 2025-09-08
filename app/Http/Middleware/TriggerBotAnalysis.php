<?php

namespace App\Http\Middleware;

use App\Jobs\AnalyzeBotRequestsJob;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TriggerBotAnalysis
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // After the response, trigger bot analysis for recent unanalyzed requests
        $this->triggerAnalysis();

        return $response;
    }

    /**
     * Trigger bot analysis for recent unanalyzed requests
     */
    protected function triggerAnalysis(): void
    {
        // Check if there are unanalyzed requests from the last minute (excluding authenticated users)
        $hasUnanalyzed = DB::table('logged_requests')
            ->whereNull('bot_analyzed_at')
            ->whereNull('user_id') // Exclude authenticated users
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if ($hasUnanalyzed) {
            // Dispatch job with small batch to analyze recent requests
            AnalyzeBotRequestsJob::dispatch(null, 10, true)
                ->delay(now()->addSeconds(5));
        }
    }
}
