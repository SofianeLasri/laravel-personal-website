<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessUserAgentJob;
use App\Services\AiProviderService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

#[CoversClass(ProcessUserAgentJob::class)]
class ProcessUserAgentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_processes_user_agent_when_bot_detected()
    {
        $userAgent = UserAgent::factory()->create(['user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->with(
                'You are a robot detector designed to output JSON. ',
                "Is this user agent a robot or a tool that is not a web browser? Please respond in the format {'is_bot': true/false}. The user agent is: {$userAgent->user_agent}"
            )
            ->andReturn(['is_bot' => true]);

        $job = new ProcessUserAgentJob($userAgent);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
            'is_bot' => true,
        ]);
    }

    public function test_successfully_processes_user_agent_when_not_bot()
    {
        $userAgent = UserAgent::factory()->create(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36']);

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->with(
                'You are a robot detector designed to output JSON. ',
                "Is this user agent a robot or a tool that is not a web browser? Please respond in the format {'is_bot': true/false}. The user agent is: {$userAgent->user_agent}"
            )
            ->andReturn(['is_bot' => false]);

        $job = new ProcessUserAgentJob($userAgent);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
            'is_bot' => false,
        ]);
    }

    public function test_handles_missing_is_bot_key_in_response()
    {
        $userAgent = UserAgent::factory()->create(['user_agent' => 'Test User Agent']);

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->andReturn(['some_other_key' => 'value']);

        $job = new ProcessUserAgentJob($userAgent);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
            'is_bot' => false, // Should default to false when key is missing
        ]);
    }

    public function test_handles_ai_service_exception()
    {
        $userAgent = UserAgent::factory()->create(['user_agent' => 'Test User Agent']);
        $exception = new Exception('AI service failed');

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->andThrow($exception);

        $job = new ProcessUserAgentJob($userAgent);

        // The job should handle the exception and not throw it
        $job->handle($mockAiService);

        // Should not create metadata when exception occurs
        $this->assertDatabaseMissing('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
        ]);
    }

    public function test_job_handles_exception_gracefully()
    {
        $userAgent = UserAgent::factory()->create(['user_agent' => 'Test User Agent']);
        $exception = new Exception('AI service failed');

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->andThrow($exception);

        $job = new ProcessUserAgentJob($userAgent);

        // Job should complete without throwing exception (it calls $this->fail() internally)
        $job->handle($mockAiService);

        $this->assertDatabaseMissing('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
        ]);
    }

    public function test_user_agent_is_correctly_passed_to_ai_service()
    {
        $userAgentString = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15';
        $userAgent = UserAgent::factory()->create(['user_agent' => $userAgentString]);

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->with(
                'You are a robot detector designed to output JSON. ',
                "Is this user agent a robot or a tool that is not a web browser? Please respond in the format {'is_bot': true/false}. The user agent is: {$userAgentString}"
            )
            ->andReturn(['is_bot' => false]);

        $job = new ProcessUserAgentJob($userAgent);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('user_agent_metadata', [
            'user_agent_id' => $userAgent->id,
            'is_bot' => false,
        ]);
    }
}
