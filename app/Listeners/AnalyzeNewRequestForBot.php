<?php

namespace App\Listeners;

use App\Jobs\AnalyzeBotRequestsJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

class AnalyzeNewRequestForBot implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The delay in seconds before processing
     */
    public int $delay = 5;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Check if the event has a LoggedRequest
        if (! property_exists($event, 'loggedRequest') || ! $event->loggedRequest instanceof LoggedRequest) {
            return;
        }

        // Dispatch job to analyze this specific request
        AnalyzeBotRequestsJob::dispatch($event->loggedRequest->id)
            ->delay(now()->addSeconds($this->delay));
    }
}
