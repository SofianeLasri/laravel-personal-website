<?php

namespace Tests\Feature\Services\BotDetection;

use App\Models\User;
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

class AuthenticatedUserExclusionTest extends TestCase
{
    use RefreshDatabase;

    private BotDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BotDetectionService;
    }

    #[Test]
    public function skips_analysis_for_authenticated_users(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create IP and user agent
        $ipAddress = IpAddress::create(['ip' => '192.168.1.100']);
        $userAgent = UserAgent::create(['user_agent' => 'Bot/1.0 (suspicious)']);
        $url = Url::create(['url' => 'https://example.com/test?random=value&debug=1']);

        // Create a request with user_id (authenticated user)
        $request = LoggedRequest::create([
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'url_id' => $url->id,
            'method' => HttpMethod::GET,
            'status_code' => 200,
        ]);

        // Update user_id directly via DB since it's not fillable
        DB::table('logged_requests')
            ->where('id', $request->id)
            ->update(['user_id' => $user->id]);

        $request->refresh();

        $result = $this->service->analyzeRequest($request);

        // Check that analysis was skipped
        $this->assertFalse($result['is_bot']);
        $this->assertEmpty($result['reasons']);
        $this->assertTrue($result['skipped'] ?? false);
        $this->assertEquals('Authenticated user', $result['skip_reason'] ?? '');

        // Check database
        $freshRequest = DB::table('logged_requests')->where('id', $request->id)->first();

        $this->assertFalse((bool) $freshRequest->is_bot_by_frequency);
        $this->assertFalse((bool) $freshRequest->is_bot_by_user_agent);
        $this->assertFalse((bool) $freshRequest->is_bot_by_parameters);
        $this->assertNotNull($freshRequest->bot_analyzed_at);

        $metadata = json_decode($freshRequest->bot_detection_metadata, true);
        $this->assertTrue($metadata['skipped'] ?? false);
        $this->assertEquals('Authenticated user', $metadata['reason'] ?? '');
    }

    #[Test]
    public function batch_analysis_excludes_authenticated_users(): void
    {
        // Create a user
        $user = User::factory()->create();

        $ipAddress = IpAddress::create(['ip' => '192.168.1.101']);
        $userAgent = UserAgent::create(['user_agent' => 'Bot/1.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create 5 requests from authenticated user
        for ($i = 0; $i < 5; $i++) {
            $request = LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
            DB::table('logged_requests')
                ->where('id', $request->id)
                ->update(['user_id' => $user->id]);
        }

        // Create 3 requests from anonymous user
        for ($i = 0; $i < 3; $i++) {
            LoggedRequest::create([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => HttpMethod::GET,
                'status_code' => 200,
            ]);
        }

        $results = $this->service->analyzeUnanalyzedRequests(10);

        // Should only analyze the 3 anonymous requests
        $this->assertCount(3, $results);

        // Verify authenticated user requests are still unanalyzed
        $authenticatedRequests = LoggedRequest::where('user_id', $user->id)->get();
        foreach ($authenticatedRequests as $request) {
            $this->assertNull($request->bot_analyzed_at);
        }

        // Verify anonymous requests were analyzed
        $anonymousRequests = LoggedRequest::whereNull('user_id')->get();
        foreach ($anonymousRequests as $request) {
            $this->assertNotNull($request->bot_analyzed_at);
        }
    }

    #[Test]
    public function high_frequency_authenticated_user_not_flagged_as_bot(): void
    {
        // Create a user
        $user = User::factory()->create();

        $ipAddress = IpAddress::create(['ip' => '192.168.1.102']);
        $userAgent = UserAgent::create(['user_agent' => 'Mozilla/5.0']);
        $url = Url::create(['url' => 'https://example.com/']);

        // Create high frequency requests from authenticated user
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

            // Update user_id directly via DB
            DB::table('logged_requests')
                ->where('id', $request->id)
                ->update(['user_id' => $user->id]);
        }

        $lastRequest = LoggedRequest::latest()->first();
        $result = $this->service->analyzeRequest($lastRequest);

        // Should be skipped, not flagged as bot
        $this->assertFalse($result['is_bot']);
        $this->assertTrue($result['skipped'] ?? false);
        $this->assertEquals('Authenticated user', $result['skip_reason'] ?? '');
    }
}
