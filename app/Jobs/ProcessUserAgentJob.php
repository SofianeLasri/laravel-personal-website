<?php

namespace App\Jobs;

use App\Models\UserAgentMetadata;
use App\Services\AiProviderService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

class ProcessUserAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly UserAgent $userAgent) {}

    public function handle(AiProviderService $aiProviderService): void
    {
        try {
            $result = $aiProviderService->prompt(
                'You are a robot detector designed to output JSON. ',
                "Is this user agent a robot or a tool that is not a web browser? Please respond in the format {'is_bot': true/false}. The user agent is: {$this->userAgent->user_agent}"
            );

            $isBot = $result['is_bot'] ?? false;

            UserAgentMetadata::create([
                'user_agent_id' => $this->userAgent->id,
                'is_bot' => $isBot,
            ]);
        } catch (Exception $e) {
            Log::error("Failed to process UserAgent: {$this->userAgent->user_agent}", [
                'exception' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
