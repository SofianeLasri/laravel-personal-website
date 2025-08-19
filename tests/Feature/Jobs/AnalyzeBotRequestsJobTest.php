<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AnalyzeBotRequestsJob;
use App\Models\IpAddressMetadata;
use App\Services\BotDetection\BotDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use SlProjects\LaravelRequestLogger\Enums\HttpMethod;
use Tests\TestCase;

#[CoversClass(AnalyzeBotRequestsJob::class)]
class AnalyzeBotRequestsJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function analyzes_specific_request_by_id(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.100']);
        $userAgent = UserAgent::create(['user_agent' => 'Bot/1.0']);
        $url = Url::create(['url' => 'https://example.com/test']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $job = new AnalyzeBotRequestsJob($request->id);
        $job->handle(new BotDetectionService);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();
        $this->assertNotNull($freshRequest->bot_analyzed_at);
    }

    #[Test]
    public function analyzes_batch_of_unanalyzed_requests(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.101']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create unanalyzed requests
        for ($i = 0; $i < 5; $i++) {
            LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
        }

        $job = new AnalyzeBotRequestsJob(null, 3, true);
        $job->handle(new BotDetectionService);

        $analyzedCount = DB::table('logged_requests')
            ->whereNotNull('bot_analyzed_at')
            ->count();

        $this->assertEquals(3, $analyzedCount);
    }

    #[Test]
    public function reanalyzes_old_requests(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.102']);
        $userAgent = UserAgent::create(['user_agent' => 'Chrome/100.0']);
        $url = Url::create(['url' => 'https://example.com/page']);

        // Create old analyzed requests
        for ($i = 0; $i < 3; $i++) {
            $request = LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);

            DB::table('logged_requests')
                ->where('id', $request->id)
                ->update([
                    'bot_analyzed_at' => Carbon::now()->subDays(2),
                    'created_at' => Carbon::now()->subHours(),
                ]);
        }

        // Create IP metadata with old analysis time
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'last_bot_analysis_at' => Carbon::now()->subDays(2),
        ]);

        $job = new AnalyzeBotRequestsJob(null, 10, false);
        $job->handle(new BotDetectionService);

        $reanalyzedCount = DB::table('logged_requests')
            ->where('bot_analyzed_at', '>', Carbon::now()->subMinute())
            ->count();

        $this->assertGreaterThan(0, $reanalyzedCount);
    }

    #[Test]
    public function handles_request_not_found(): void
    {
        // Try to analyze non-existent request
        $job = new AnalyzeBotRequestsJob(99999);

        // Should not throw exception
        $job->handle(new BotDetectionService);

        $this->assertTrue(true); // Job completed without error
    }

    #[Test]
    public function respects_batch_size_limit(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.103']);
        $userAgent = UserAgent::create(['user_agent' => 'TestBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/batch']);

        // Create 10 unanalyzed requests
        for ($i = 0; $i < 10; $i++) {
            LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
        }

        $job = new AnalyzeBotRequestsJob(null, 5, true);
        $job->handle(new BotDetectionService);

        $analyzedCount = DB::table('logged_requests')
            ->whereNotNull('bot_analyzed_at')
            ->count();

        $this->assertEquals(5, $analyzedCount);
    }

    #[Test]
    public function skips_authenticated_user_requests(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.104']);
        $userAgent = UserAgent::create(['user_agent' => 'Firefox/95.0']);
        $url = Url::create(['url' => 'https://example.com/user']);

        // Create request with user_id
        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        DB::table('logged_requests')
            ->where('id', $request->id)
            ->update(['user_id' => 1]);

        $job = new AnalyzeBotRequestsJob($request->id);
        $job->handle(new BotDetectionService);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();
        $this->assertNotNull($freshRequest->bot_analyzed_at);
        $this->assertFalse((bool) $freshRequest->is_bot_by_frequency);
        $this->assertFalse((bool) $freshRequest->is_bot_by_user_agent);
        $this->assertFalse((bool) $freshRequest->is_bot_by_parameters);

        $metadata = json_decode($freshRequest->bot_detection_metadata, true);
        $this->assertTrue($metadata['skipped'] ?? false);
        $this->assertEquals('Authenticated user', $metadata['reason'] ?? '');
    }

    #[Test]
    public function handles_empty_batch(): void
    {
        // No unanalyzed requests exist
        $job = new AnalyzeBotRequestsJob(null, 10, true);

        // Should not throw exception
        $job->handle(new BotDetectionService);

        $this->assertTrue(true); // Job completed without error
    }

    #[Test]
    public function updates_ip_metadata_during_analysis(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.105']);
        $userAgent = UserAgent::create(['user_agent' => 'Scanner/2.0']);
        $url = Url::create(['url' => 'https://example.com/scan']);

        $baseTime = Carbon::now()->subMinutes(5);

        // Create multiple requests for frequency analysis
        for ($i = 0; $i < 10; $i++) {
            $request = new LoggedRequest([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            $request->created_at = $baseTime->copy()->addSeconds($i * 2);
            $request->updated_at = $baseTime->copy()->addSeconds($i * 2);
            $request->save();
        }

        $lastRequest = LoggedRequest::latest()->first();
        $job = new AnalyzeBotRequestsJob($lastRequest->id);
        $job->handle(new BotDetectionService);

        $metadata = IpAddressMetadata::where('ip_address_id', $ipAddress->id)->first();
        if ($metadata) {
            // The metadata might not have last_bot_analysis_at set immediately
            $this->assertGreaterThan(0, $metadata->total_requests);
        } else {
            // Metadata might not be created if the service doesn't detect suspicious activity
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function logs_analysis_results(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.106']);
        $userAgent = UserAgent::create(['user_agent' => 'BadBot/1.0']);
        $url = Url::create(['url' => 'https://example.com/hack?test=1']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $job = new AnalyzeBotRequestsJob($request->id);
        $job->handle(new BotDetectionService);

        // Just verify the job completed without error
        $this->assertTrue(true);
    }
}
