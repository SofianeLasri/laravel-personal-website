<?php

namespace Tests\Feature\Services\BotDetection;

use App\Models\IpAddressMetadata;
use App\Services\BotDetection\BotDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
