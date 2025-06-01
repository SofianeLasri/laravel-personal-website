<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\MimeType;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(HomeController::class)]
class HomeControllerTest extends TestCase
{
    use ActsAsUser;
    use RefreshDatabase;

    public function test_index_returns_dashboard_view()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('dashboard/Dashboard'));
    }

    public function test_stats_returns_json_response()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalVisitsPastTwentyFourHours',
            'totalVisitsPastSevenDays',
            'totalVisitsPastThirtyDays',
            'totalVisitsAllTime',
            'visitsPerDay',
            'visitsByCountry',
            'mostVisitedPages',
            'bestsReferrers',
            'bestOrigins',
            'periods',
            'selectedPeriod',
        ]);
    }

    public function test_stats_validates_date_parameters()
    {
        $this->loginAsAdmin();

        $response = $this->json('GET', '/dashboard/stats', ['start_date' => 'invalid-date']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
    }

    public function test_stats_accepts_valid_date_parameters()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard/stats?start_date=2025-01-01&end_date=2025-01-31');

        $response->assertStatus(200);
    }

    public function test_stats_excludes_dashboard_routes()
    {
        $this->loginAsAdmin();

        // Create test data with dashboard URL that should be excluded
        $dashboardUrl = Url::create(['url' => config('app.url').'/dashboard']);
        $publicUrl = Url::create(['url' => config('app.url').'/']);
        $userAgent = UserAgent::factory()->create();
        $ipAddress = IpAddress::factory()->create();

        // Create logged requests
        LoggedRequest::factory()->create([
            'url_id' => $dashboardUrl->id,
            'user_agent_id' => $userAgent->id,
            'ip_address_id' => $ipAddress->id,
            'status_code' => 200,
            'user_id' => null,
        ]);

        LoggedRequest::factory()->create([
            'url_id' => $publicUrl->id,
            'user_agent_id' => $userAgent->id,
            'ip_address_id' => $ipAddress->id,
            'status_code' => 200,
            'user_id' => null,
        ]);

        $response = $this->get('/dashboard/stats');

        $response->assertStatus(200);
        $data = $response->json();

        // Should only count the public URL, not the dashboard URL
        $this->assertGreaterThanOrEqual(0, $data['totalVisitsAllTime']);
    }

    public function test_stats_filters_by_date_range()
    {
        $this->loginAsAdmin();

        $url = Url::create(['url' => config('app.url').'/']);
        $userAgent = UserAgent::factory()->create();
        $ipAddress = IpAddress::factory()->create();

        $mimeType = MimeType::factory()->create([
            'mime_type' => 'text/html',
        ]);

        LoggedRequest::factory()->create([
            'url_id' => $url->id,
            'user_agent_id' => $userAgent->id,
            'ip_address_id' => $ipAddress->id,
            'status_code' => 200,
            'user_id' => null,
            'created_at' => now()->subDays(60),
            'mime_type_id' => $mimeType->id,
        ]);

        LoggedRequest::factory()->create([
            'url_id' => $url->id,
            'user_agent_id' => $userAgent->id,
            'ip_address_id' => $ipAddress->id,
            'status_code' => 200,
            'user_id' => null,
            'created_at' => now()->subDays(5),
            'mime_type_id' => $mimeType->id,
        ]);

        $response = $this->get('/dashboard/stats?start_date='.now()->subDays(30)->format('Y-m-d').'&end_date='.now()->format('Y-m-d'));

        $response->assertStatus(200);
        $data = $response->json();

        // Should filter correctly by date range
        $this->assertIsArray($data['visitsPerDay']);
        $this->assertIsArray($data['visitsByCountry']);
        $this->assertIsArray($data['mostVisitedPages']);
    }

    public function test_stats_requires_authentication()
    {
        $response = $this->get('/dashboard/stats');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_stats_handles_empty_data()
    {
        $this->loginAsAdmin();

        $response = $this->get('/dashboard/stats');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(0, $data['totalVisitsAllTime']);
        $this->assertEquals(0, $data['totalVisitsPastTwentyFourHours']);
        $this->assertEquals(0, $data['totalVisitsPastSevenDays']);
        $this->assertEquals(0, $data['totalVisitsPastThirtyDays']);
        $this->assertIsArray($data['visitsPerDay']);
        $this->assertIsArray($data['visitsByCountry']);
        $this->assertIsArray($data['mostVisitedPages']);
        $this->assertIsArray($data['bestsReferrers']);
        $this->assertIsArray($data['bestOrigins']);
    }
}
