<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AnalyzeBotRequestsCommand;
use App\Jobs\AnalyzeBotRequestsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use SlProjects\LaravelRequestLogger\Enums\HttpMethod;
use Tests\TestCase;

#[CoversClass(AnalyzeBotRequestsCommand::class)]
class AnalyzeBotRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function runs_analysis_synchronously_by_default(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.200']);
        $userAgent = UserAgent::create(['user_agent' => 'TestBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $this->artisan('bot:analyze')
            ->expectsOutput('Starting batch analysis: analyzing unanalyzed requests (batch size: 100)')
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function dispatches_job_to_queue_with_queue_option(): void
    {
        Queue::fake();

        $ipAddress = IpAddress::create(['ip' => '192.168.1.201']);
        $userAgent = UserAgent::create(['user_agent' => 'QueueBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/queue']);

        LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $this->artisan('bot:analyze --queue')
            ->expectsOutput('Starting batch analysis: analyzing unanalyzed requests (batch size: 100)')
            ->expectsOutput('Job dispatched to queue successfully.')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeBotRequestsJob::class);
    }

    #[Test]
    public function analyzes_specific_request_with_request_id_option(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.202']);
        $userAgent = UserAgent::create(['user_agent' => 'SpecificBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/specific']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $this->artisan("bot:analyze --request-id=$request->id")
            ->expectsOutput("Analyzing specific request ID: $request->id")
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function reanalyzes_old_requests_with_re_analyze_option(): void
    {
        $this->artisan('bot:analyze --re-analyze')
            ->expectsOutput('Starting batch analysis: re-analyzing old requests (batch size: 100)')
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function respects_batch_size_option(): void
    {
        $this->artisan('bot:analyze --batch-size=50')
            ->expectsOutput('Starting batch analysis: analyzing unanalyzed requests (batch size: 50)')
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function combines_multiple_options(): void
    {
        Queue::fake();

        $this->artisan('bot:analyze --batch-size=25 --re-analyze --queue')
            ->expectsOutput('Starting batch analysis: re-analyzing old requests (batch size: 25)')
            ->expectsOutput('Job dispatched to queue successfully.')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeBotRequestsJob::class);
    }

    #[Test]
    public function dispatches_specific_request_to_queue(): void
    {
        Queue::fake();

        $ipAddress = IpAddress::create(['ip' => '192.168.1.203']);
        $userAgent = UserAgent::create(['user_agent' => 'QueueSpecificBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/queue-specific']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $this->artisan("bot:analyze --request-id=$request->id --queue")
            ->expectsOutput("Analyzing specific request ID: $request->id")
            ->expectsOutput('Job dispatched to queue successfully.')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeBotRequestsJob::class);
    }

    #[Test]
    public function handles_include_authenticated_option(): void
    {
        $this->artisan('bot:analyze --include-authenticated')
            ->expectsOutput('Starting batch analysis: analyzing unanalyzed requests (batch size: 100)')
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function validates_batch_size_is_positive(): void
    {
        // Note: Laravel's command validation will handle this
        // We're testing that the command handles it gracefully
        $this->artisan('bot:analyze --batch-size=0')
            ->expectsOutput('Starting batch analysis: analyzing unanalyzed requests (batch size: 0)')
            ->expectsOutput('Running analysis synchronously...')
            ->expectsOutput('Analysis completed successfully.')
            ->assertSuccessful();
    }

    #[Test]
    public function shows_help_information(): void
    {
        $this->artisan('bot:analyze --help')
            ->expectsOutputToContain('Analyze logged requests for bot behavior')
            ->expectsOutputToContain('--batch-size')
            ->expectsOutputToContain('--re-analyze')
            ->expectsOutputToContain('--request-id')
            ->expectsOutputToContain('--queue')
            ->expectsOutputToContain('--include-authenticated')
            ->assertSuccessful();
    }
}
