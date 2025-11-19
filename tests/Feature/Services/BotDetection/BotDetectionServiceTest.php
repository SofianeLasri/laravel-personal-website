<?php

namespace Tests\Feature\Services\BotDetection;

use App\Models\ExtendedLoggedRequest;
use App\Models\IpAddressMetadata;
use App\Services\BotDetection\BotDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use SlProjects\LaravelRequestLogger\Enums\HttpMethod;
use Tests\TestCase;

class BotDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BotDetectionService;
    }

    #[Test]
    public function detects_high_frequency_requests_as_bot(): void
    {
        // Create IP and user agent
        $ipAddress = IpAddress::create(['ip' => '192.168.1.1']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create multiple requests in quick succession (1 per second)
        $baseTime = Carbon::now()->subMinutes(5);
        for ($i = 0; $i < 50; $i++) {
            $request = new LoggedRequest([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            $request->created_at = $baseTime->copy()->addSeconds($i);
            $request->updated_at = $baseTime->copy()->addSeconds($i);
            $request->save();
        }

        $lastRequest = LoggedRequest::latest()->first();
        $result = $this->service->analyzeRequest($lastRequest);

        $freshRequest = DB::table('logged_requests')->where('id', $lastRequest->id)->first();

        // Debug if test fails
        if (! $result['is_bot']) {
            $metadata = json_decode($freshRequest->bot_detection_metadata, true);
            $this->fail('Bot not detected. Frequency analysis: '.json_encode($metadata['frequency_analysis'] ?? []));
        }

        $this->assertTrue($result['is_bot'], 'Expected request to be detected as bot');
        $this->assertNotEmpty($result['reasons']);
        $this->assertTrue((bool) $freshRequest->is_bot_by_frequency, 'Expected bot detection by frequency');
    }

    #[Test]
    public function detects_requests_with_no_browser_and_high_frequency(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.2']);
        // Use a minimal user agent without browser info
        $userAgent = UserAgent::create([
            'user_agent' => 'curl/7.68.0',
        ]);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create high frequency requests
        $baseTime = Carbon::now()->subMinutes(2);
        for ($i = 0; $i < 30; $i++) {
            $request = new LoggedRequest([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            $request->created_at = $baseTime->copy()->addSeconds($i);
            $request->updated_at = $baseTime->copy()->addSeconds($i);
            $request->save();
        }

        $lastRequest = LoggedRequest::latest()->first();
        $result = $this->service->analyzeRequest($lastRequest);

        $freshRequest = DB::table('logged_requests')->where('id', $lastRequest->id)->first();

        $this->assertTrue($result['is_bot'], 'Expected bot detection for high frequency with no browser');
        $this->assertTrue(
            (bool) $freshRequest->is_bot_by_user_agent || (bool) $freshRequest->is_bot_by_frequency,
            'Expected detection by user agent or frequency'
        );
    }

    #[Test]
    public function detects_suspicious_url_parameters(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.3']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create([
            'url' => 'https://example.com/projects?a1b2c3d4e5f6g7h8i9j0=randomvalue&debug=1',
        ]);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $result = $this->service->analyzeRequest($request);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        $this->assertTrue($result['is_bot']);
        $this->assertTrue((bool) $freshRequest->is_bot_by_parameters);
    }

    #[Test]
    public function does_not_flag_normal_requests_as_bot(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.4']);
        $userAgent = UserAgent::create([
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        $url = Url::create(['url' => 'https://example.com/projects?page=1&search=laravel']);

        // Create normal frequency requests (1 request every 10 minutes)
        $baseTime = Carbon::now()->subHours(1);
        for ($i = 0; $i < 4; $i++) {
            $request = new LoggedRequest([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            $request->created_at = $baseTime->copy()->addMinutes($i * 10);
            $request->updated_at = $baseTime->copy()->addMinutes($i * 10);
            $request->save();
        }

        $lastRequest = LoggedRequest::latest()->first();
        $result = $this->service->analyzeRequest($lastRequest);

        $freshRequest = DB::table('logged_requests')->where('id', $lastRequest->id)->first();

        $this->assertFalse($result['is_bot']);
        $this->assertEmpty($result['reasons']);
        $this->assertFalse((bool) $freshRequest->is_bot_by_frequency);
        $this->assertFalse((bool) $freshRequest->is_bot_by_user_agent);
        $this->assertFalse((bool) $freshRequest->is_bot_by_parameters);
    }

    #[Test]
    public function batch_analysis_processes_multiple_requests(): void
    {
        // Create multiple unanalyzed requests
        $ipAddress = IpAddress::create(['ip' => '192.168.1.5']);
        $userAgent = UserAgent::create(['user_agent' => 'Bot/1.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        for ($i = 0; $i < 10; $i++) {
            LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
        }

        $results = $this->service->analyzeUnanalyzedRequests(5);

        $this->assertCount(5, $results);

        // Verify that the requests were marked as analyzed
        $analyzedCount = LoggedRequest::whereNotNull('bot_analyzed_at')->count();
        $this->assertEquals(5, $analyzedCount);
    }

    #[Test]
    public function updates_ip_metadata_during_analysis(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.6']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create requests with 5 second intervals
        $baseTime = Carbon::now()->subMinutes(10);
        for ($i = 0; $i < 10; $i++) {
            $request = new LoggedRequest([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            $request->created_at = $baseTime->copy()->addSeconds($i * 5);
            $request->updated_at = $baseTime->copy()->addSeconds($i * 5);
            $request->save();
        }

        $lastRequest = LoggedRequest::latest()->first();
        $this->service->analyzeRequest($lastRequest);

        $ipMetadata = IpAddressMetadata::where('ip_address_id', $ipAddress->id)->first();

        $this->assertNotNull($ipMetadata);
        $this->assertNotNull($ipMetadata->avg_request_interval);
        $this->assertGreaterThan(0, $ipMetadata->total_requests);
        $this->assertNotNull($ipMetadata->first_seen_at);
        $this->assertNotNull($ipMetadata->last_seen_at);
    }

    #[Test]
    public function detects_suspicious_referer_containing_myworkdayjobs(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.7']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);
        $refererUrl = Url::create(['url' => 'https://example.myworkdayjobs.com/recruiting']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'referer_url_id' => $refererUrl->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $result = $this->service->analyzeRequest($request);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        $this->assertTrue($result['is_bot'], 'Expected bot detection for suspicious referer');
        $this->assertTrue((bool) $freshRequest->is_bot_by_user_agent, 'Expected detection by user agent (referer)');
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('Suspicious referer', implode(' ', $result['reasons']));
    }

    #[Test]
    public function detects_suspicious_referer_case_insensitive(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.8']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);
        $refererUrl = Url::create(['url' => 'https://example.MyWorkDayJobs.com/careers']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'referer_url_id' => $refererUrl->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $result = $this->service->analyzeRequest($request);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        $this->assertTrue($result['is_bot'], 'Expected bot detection for suspicious referer (case insensitive)');
        $this->assertTrue((bool) $freshRequest->is_bot_by_user_agent);

        $metadata = json_decode($freshRequest->bot_detection_metadata, true);
        $this->assertNotNull($metadata['referer_analysis']);
        $this->assertTrue($metadata['referer_analysis']['is_suspicious']);
        $this->assertEquals('myworkdayjobs', $metadata['referer_analysis']['matched_term']);
    }

    #[Test]
    public function does_not_flag_normal_referer_as_bot(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.9']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/projects']);
        $refererUrl = Url::create(['url' => 'https://google.com/']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'referer_url_id' => $refererUrl->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $result = $this->service->analyzeRequest($request);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        $this->assertFalse($result['is_bot'], 'Expected no bot detection for normal referer');
        $this->assertFalse((bool) $freshRequest->is_bot_by_user_agent);
        $this->assertFalse((bool) $freshRequest->is_bot_by_frequency);
        $this->assertFalse((bool) $freshRequest->is_bot_by_parameters);
    }

    #[Test]
    public function handles_requests_without_referer(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.10']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'referer_url_id' => null,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        $result = $this->service->analyzeRequest($request);

        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        // Should complete analysis without errors
        $this->assertNotNull($freshRequest->bot_analyzed_at);
        $this->assertFalse($result['is_bot'], 'Expected no bot detection for request without referer');
    }

    #[Test]
    public function reanalyzes_requests_for_ips_never_analyzed_before(): void
    {
        // Mark all existing IPs as recently analyzed to isolate this test
        IpAddressMetadata::query()->update(['last_bot_analysis_at' => now()]);

        $ipAddress = IpAddress::factory()->create();
        $userAgent = UserAgent::factory()->create();
        $url = Url::factory()->create();

        // Create IP metadata without last_bot_analysis_at
        $ipMetadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subHours(2),
            'last_seen_at' => now(),
            'total_requests' => 10,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => null,
        ]);

        // Create recent requests with explicit timestamps
        $request1 = ExtendedLoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);
        DB::table('logged_requests')->where('id', $request1->id)->update([
            'created_at' => now()->subHours(1),
        ]);

        $request2 = ExtendedLoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);
        DB::table('logged_requests')->where('id', $request2->id)->update([
            'created_at' => now()->subMinutes(30),
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        // Filter results to only those from our test IP
        $testResults = $results->filter(fn ($r) => in_array($r['request_id'], [$request1->id, $request2->id]));

        $this->assertCount(2, $testResults);
        $this->assertEquals($request2->id, $testResults->first()['request_id']); // Most recent first

        // Verify last_bot_analysis_at was updated
        $ipMetadata->refresh();
        $this->assertNotNull($ipMetadata->last_bot_analysis_at);
    }

    #[Test]
    public function reanalyzes_requests_for_ips_analyzed_long_ago(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.12']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create IP metadata with old last_bot_analysis_at
        $ipMetadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now(),
            'total_requests' => 50,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => now()->subHours(48), // 2 days ago
        ]);

        // Create recent requests
        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
            'created_at' => now()->subHours(2),
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        $this->assertCount(1, $results);
        $this->assertEquals($request->id, $results->first()['request_id']);

        // Verify last_bot_analysis_at was updated
        $oldAnalysisTime = $ipMetadata->last_bot_analysis_at;
        $ipMetadata->refresh();
        $this->assertNotEquals($oldAnalysisTime, $ipMetadata->last_bot_analysis_at);
    }

    #[Test]
    public function does_not_reanalyze_recently_analyzed_ips(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.13']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create IP metadata with recent last_bot_analysis_at
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subHours(10),
            'last_seen_at' => now(),
            'total_requests' => 20,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => now()->subHours(12), // 12 hours ago (within 24h window)
        ]);

        // Create recent requests
        LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
            'created_at' => now()->subHours(2),
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        $this->assertCount(0, $results);
    }

    #[Test]
    public function excludes_authenticated_users_from_reanalysis(): void
    {
        // Mark all existing IPs as recently analyzed to isolate this test
        IpAddressMetadata::query()->update(['last_bot_analysis_at' => now()]);

        $ipAddress = IpAddress::factory()->create();
        $userAgent = UserAgent::factory()->create();
        $url = Url::factory()->create();

        // Create IP metadata without last_bot_analysis_at
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subHours(2),
            'last_seen_at' => now(),
            'total_requests' => 5,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => null,
        ]);

        // Create request with authenticated user
        $authenticatedRequest = ExtendedLoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'user_id' => 1, // Authenticated user
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);
        DB::table('logged_requests')->where('id', $authenticatedRequest->id)->update([
            'created_at' => now()->subHours(1),
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        // Verify our authenticated request is NOT in the results
        $requestIds = $results->pluck('request_id');
        $this->assertNotContains($authenticatedRequest->id, $requestIds);
    }

    #[Test]
    public function only_reanalyzes_requests_created_after_cutoff_time(): void
    {
        // Mark all existing IPs as recently analyzed to isolate this test
        IpAddressMetadata::query()->update(['last_bot_analysis_at' => now()]);

        $ipAddress = IpAddress::factory()->create();
        $userAgent = UserAgent::factory()->create();
        $url = Url::factory()->create();

        // Create IP metadata without last_bot_analysis_at
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subDays(5),
            'last_seen_at' => now(),
            'total_requests' => 100,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => null,
        ]);

        // Create old request (before cutoff)
        $oldRequest = ExtendedLoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);
        DB::table('logged_requests')->where('id', $oldRequest->id)->update([
            'created_at' => now()->subHours(48), // 2 days ago
        ]);

        // Create recent request (after cutoff)
        $recentRequest = ExtendedLoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);
        DB::table('logged_requests')->where('id', $recentRequest->id)->update([
            'created_at' => now()->subHours(12), // 12 hours ago
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        // Filter results to only those from our test IP
        $testResults = $results->filter(fn ($r) => in_array($r['request_id'], [$oldRequest->id, $recentRequest->id]));

        $this->assertCount(1, $testResults);
        // Verify that only the recent request was analyzed
        $this->assertEquals($recentRequest->id, $testResults->first()['request_id']);
    }

    #[Test]
    public function respects_limit_parameter(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.16']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create IP metadata without last_bot_analysis_at
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subHours(10),
            'last_seen_at' => now(),
            'total_requests' => 150,
            'avg_request_interval' => 2.0,
            'last_bot_analysis_at' => null,
        ]);

        // Create 20 recent requests
        for ($i = 0; $i < 20; $i++) {
            LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
                'created_at' => now()->subHours(1)->addMinutes($i),
            ]);
        }

        $results = $this->service->reanalyzeOldRequests(24, 10);

        $this->assertCount(10, $results);
    }

    #[Test]
    public function returns_empty_collection_when_no_ips_need_reanalysis(): void
    {
        $ipAddress = IpAddress::create(['ip' => '192.168.1.17']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create IP metadata with very recent last_bot_analysis_at
        IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'first_seen_at' => now()->subHours(5),
            'last_seen_at' => now(),
            'total_requests' => 10,
            'avg_request_interval' => 5.0,
            'last_bot_analysis_at' => now()->subMinutes(30), // 30 minutes ago
        ]);

        // Create recent requests
        LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
            'created_at' => now()->subMinutes(15),
        ]);

        $results = $this->service->reanalyzeOldRequests(24, 100);

        $this->assertCount(0, $results);
        $this->assertInstanceOf(Collection::class, $results);
    }
}
