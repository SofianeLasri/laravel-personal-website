<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\User;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

class RequestLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_mark_requests_as_bot(): void
    {
        $this->actingAs($this->user);

        // Créer quelques requêtes de test directement
        $requestIds = [];
        for ($i = 0; $i < 3; $i++) {
            $ipAddress = IpAddress::create(['ip' => '127.0.0.'.$i]);
            $userAgent = UserAgent::create(['user_agent' => 'Test User Agent '.$i]);
            $url = Url::create(['url' => 'http://example.com/test'.$i]);

            // Créer directement dans la base de données
            $requestId = DB::table('logged_requests')->insertGetId([
                'ip_address_id' => $ipAddress->id,
                'user_agent_id' => $userAgent->id,
                'url_id' => $url->id,
                'method' => 'GET',
                'status_code' => 200,
                'is_bot_by_user_agent' => 0,
                'is_bot_by_frequency' => 0,
                'is_bot_by_parameters' => 0,
                'bot_detection_metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $requestIds[] = $requestId;
        }

        // Marquer les requêtes comme bot
        $response = $this->postJson(route('dashboard.request-logs.mark-as-bot'), [
            'request_ids' => $requestIds,
        ]);

        $response->assertOk();

        // Debug output
        $responseData = $response->json();
        $this->assertEquals(3, $responseData['updated_count'], 'Expected 3 requests to be updated, but got '.$responseData['updated_count']);

        // Vérifier que les requêtes ont été marquées comme bot
        foreach ($requestIds as $requestId) {
            // Force a fresh query
            $request = DB::connection()->table('logged_requests')->where('id', $requestId)->first();
            $this->assertNotNull($request, "Request with ID $requestId not found");

            // Debug output
            if (! $request->is_bot_by_user_agent) {
                $allData = (array) $request;
                $this->fail("Request $requestId not marked as bot. Full data: ".json_encode($allData));
            }

            $this->assertEquals(1, $request->is_bot_by_user_agent, "Request $requestId not marked as bot");

            $metadata = json_decode($request->bot_detection_metadata, true);
            $this->assertNotNull($metadata, "Bot metadata is null for request $requestId");
            $this->assertTrue($metadata['manually_flagged'] ?? false);
            $this->assertEquals($this->user->id, $metadata['flagged_by'] ?? null);
            $this->assertEquals('Manuellement marqué comme bot via le dashboard', $metadata['reason'] ?? '');
        }
    }

    public function test_mark_as_bot_requires_authentication(): void
    {
        $response = $this->postJson(route('dashboard.request-logs.mark-as-bot'), [
            'request_ids' => [1, 2, 3],
        ]);

        $response->assertUnauthorized();
    }

    public function test_mark_as_bot_validates_request_ids(): void
    {
        $this->actingAs($this->user);

        // Test avec request_ids vide
        $response = $this->postJson(route('dashboard.request-logs.mark-as-bot'), [
            'request_ids' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['request_ids']);

        // Test avec des IDs invalides
        $response = $this->postJson(route('dashboard.request-logs.mark-as-bot'), [
            'request_ids' => [999999, 888888],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['request_ids.0', 'request_ids.1']);
    }

    public function test_can_view_request_logs_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('dashboard.request-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/requests-log/List')
            ->has('requests')
            ->has('filters')
        );
    }

    public function test_request_logs_page_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.request-logs.index'));

        $response->assertRedirect(route('login'));
    }
}
