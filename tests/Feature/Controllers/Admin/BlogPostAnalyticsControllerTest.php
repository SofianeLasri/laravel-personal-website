<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\BlogPostAnalyticsController;
use App\Models\BlogPost;
use App\Models\IpAddressMetadata;
use App\Models\User;
use App\Models\UserAgentMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\MimeType;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

#[CoversClass(BlogPostAnalyticsController::class)]
class BlogPostAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Helper method to create a URL record.
     */
    private function createUrl(string $url): Url
    {
        return Url::create(['url' => $url]);
    }

    /**
     * Helper method to create an IP address with optional metadata.
     */
    private function createIpAddress(string $ip, ?string $countryCode = null): IpAddress
    {
        $ipAddress = IpAddress::create(['ip' => $ip]);

        if ($countryCode !== null) {
            IpAddressMetadata::create([
                'ip_address_id' => $ipAddress->id,
                'country_code' => $countryCode,
            ]);
        }

        return $ipAddress;
    }

    /**
     * Helper method to create a user agent with metadata.
     */
    private function createUserAgent(string $userAgent, bool $isBot = false): UserAgent
    {
        $ua = UserAgent::firstOrCreate(['user_agent' => $userAgent]);

        UserAgentMetadata::firstOrCreate(
            ['user_agent_id' => $ua->id],
            ['is_bot' => $isBot]
        );

        return $ua;
    }

    /**
     * Helper method to create a logged request with all necessary relationships.
     */
    private function createLoggedRequest(
        Url $url,
        IpAddress $ipAddress,
        ?UserAgent $userAgent = null,
        ?int $userId = null,
        bool $isBotByFrequency = false,
        bool $isBotByUserAgent = false,
        bool $isBotByParameters = false,
        int $statusCode = 200,
        ?\DateTimeInterface $createdAt = null
    ): LoggedRequest {
        if ($userAgent === null) {
            $userAgent = $this->createUserAgent('Mozilla/5.0 Test Browser');
        }

        $mimeType = MimeType::firstOrCreate(['mime_type' => 'text/html']);

        // Use DB::table()->insert() instead of Model::create() to bypass mass assignment protection
        $id = \DB::table('logged_requests')->insertGetId([
            'url_id' => $url->id,
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'mime_type_id' => $mimeType->id,
            'user_id' => $userId,
            'method' => 'GET',
            'status_code' => $statusCode,
            'is_bot_by_frequency' => $isBotByFrequency ? 1 : 0,
            'is_bot_by_user_agent' => $isBotByUserAgent ? 1 : 0,
            'is_bot_by_parameters' => $isBotByParameters ? 1 : 0,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return LoggedRequest::find($id);
    }

    // =============================================================================
    // Tests for getViews() method
    // =============================================================================

    #[Test]
    public function get_views_requires_authentication(): void
    {
        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [1],
        ]));

        $response->assertUnauthorized();
    }

    #[Test]
    public function get_views_validates_ids_parameter_is_required(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('dashboard.api.blog-posts.views'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    #[Test]
    public function get_views_validates_ids_must_be_array(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => 'not-an-array',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    #[Test]
    public function get_views_validates_ids_must_be_integers(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => ['not-an-integer', 'also-not-an-integer'],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    #[Test]
    public function get_views_validates_ids_must_exist_in_database(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [99999, 99998], // Non-existent IDs
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    #[Test]
    public function get_views_validates_date_from_must_be_valid_date(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_from' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from']);
    }

    #[Test]
    public function get_views_validates_date_to_must_be_valid_date(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_to' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    #[Test]
    public function get_views_validates_date_to_must_be_after_or_equal_date_from(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_from' => '2025-11-10',
            'date_to' => '2025-11-09', // Before date_from
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    #[Test]
    public function get_views_returns_correct_structure_for_single_blog_post(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);

        // Create some visits for the blog post
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));
        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        $this->createLoggedRequest($url, $ip1);
        $this->createLoggedRequest($url, $ip2);
        $this->createLoggedRequest($url, $ip1); // Duplicate IP

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'views',
        ]);

        // Should count unique IPs only
        $this->assertEquals(2, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_returns_correct_counts_for_multiple_blog_posts(): void
    {
        $this->actingAs($this->user);

        $blogPost1 = BlogPost::factory()->create(['slug' => 'post-one']);
        $blogPost2 = BlogPost::factory()->create(['slug' => 'post-two']);
        $blogPost3 = BlogPost::factory()->create(['slug' => 'post-three']);

        // Create visits for post 1
        $url1 = $this->createUrl(route('public.blog.post', ['slug' => $blogPost1->slug]));
        $this->createLoggedRequest($url1, $this->createIpAddress('192.168.1.1'));
        $this->createLoggedRequest($url1, $this->createIpAddress('192.168.1.2'));

        // Create visits for post 2
        $url2 = $this->createUrl(route('public.blog.post', ['slug' => $blogPost2->slug]));
        $this->createLoggedRequest($url2, $this->createIpAddress('192.168.1.3'));

        // Post 3 has no visits

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost1->id, $blogPost2->id, $blogPost3->id],
        ]));

        $response->assertOk();

        $views = $response->json('views');
        $this->assertEquals(2, $views[$blogPost1->id]);
        $this->assertEquals(1, $views[$blogPost2->id]);
        $this->assertEquals(0, $views[$blogPost3->id]);
    }

    #[Test]
    public function get_views_returns_zero_for_blog_post_with_no_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'no-visits']);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
        ]));

        $response->assertOk();
        $this->assertEquals(0, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_excludes_bot_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');
        $ip3 = $this->createIpAddress('192.168.1.3');
        $ip4 = $this->createIpAddress('192.168.1.4');
        $ip5 = $this->createIpAddress('192.168.1.5');

        // Real user visits
        $this->createLoggedRequest($url, $ip1);
        $this->createLoggedRequest($url, $ip2);

        // Bot visits (should be excluded)
        $this->createLoggedRequest($url, $ip3, isBotByUserAgent: true);
        $this->createLoggedRequest($url, $ip4, isBotByFrequency: true);
        $this->createLoggedRequest($url, $ip5, isBotByParameters: true);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
        ]));

        $response->assertOk();
        // Should only count the 2 non-bot visits
        $this->assertEquals(2, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_excludes_authenticated_user_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        $authenticatedUser = User::factory()->create();

        // Guest visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1'), userId: null);
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2'), userId: null);

        // Authenticated user visits (should be excluded)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3'), userId: $authenticatedUser->id);

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
        ]));

        $response->assertOk();
        // Should only count the 2 guest visits
        $this->assertEquals(2, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_filters_by_date_from(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Old visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1'), createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2'), createdAt: now()->subDays(5));

        // Recent visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3'), createdAt: now()->subDays(2));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4'), createdAt: now()->subDay());

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_from' => now()->subDays(3)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits from the last 3 days
        $this->assertEquals(2, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_filters_by_date_to(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Old visits (should be included)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1'), createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2'), createdAt: now()->subDays(5));

        // Recent visits (should be excluded)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3'), createdAt: now()->subDay());
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4'), createdAt: now());

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_to' => now()->subDays(4)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits up to 4 days ago
        $this->assertEquals(2, $response->json("views.{$blogPost->id}"));
    }

    #[Test]
    public function get_views_filters_by_date_range(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Before range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1'), createdAt: now()->subDays(10));

        // Within range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2'), createdAt: now()->subDays(7));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3'), createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4'), createdAt: now()->subDays(3));

        // After range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.5'), createdAt: now()->subDay());

        $response = $this->getJson(route('dashboard.api.blog-posts.views', [
            'ids' => [$blogPost->id],
            'date_from' => now()->subDays(8)->format('Y-m-d'),
            'date_to' => now()->subDays(2)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits within the date range
        $this->assertEquals(3, $response->json("views.{$blogPost->id}"));
    }

    // =============================================================================
    // Tests for getDetailedAnalytics() method
    // =============================================================================

    #[Test]
    public function get_detailed_analytics_requires_authentication(): void
    {
        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertUnauthorized();
    }

    #[Test]
    public function get_detailed_analytics_returns_404_for_non_existent_blog_post(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => 99999, // Non-existent ID
        ]));

        $response->assertNotFound();
    }

    #[Test]
    public function get_detailed_analytics_validates_date_from_must_be_valid_date(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_from' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_from']);
    }

    #[Test]
    public function get_detailed_analytics_validates_date_to_must_be_valid_date(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_to' => 'not-a-date',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    #[Test]
    public function get_detailed_analytics_validates_date_to_must_be_after_or_equal_date_from(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create();

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_from' => '2025-11-10',
            'date_to' => '2025-11-09', // Before date_from
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_to']);
    }

    #[Test]
    public function get_detailed_analytics_returns_complete_structure_with_data(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Create visits from different countries on different days
        $this->createLoggedRequest(
            $url,
            $this->createIpAddress('192.168.1.1', 'FR'),
            createdAt: now()->subDays(5)
        );
        $this->createLoggedRequest(
            $url,
            $this->createIpAddress('192.168.1.2', 'FR'),
            createdAt: now()->subDays(5)
        );
        $this->createLoggedRequest(
            $url,
            $this->createIpAddress('192.168.1.3', 'US'),
            createdAt: now()->subDays(3)
        );
        $this->createLoggedRequest(
            $url,
            $this->createIpAddress('192.168.1.4', 'DE'),
            createdAt: now()->subDay()
        );

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'total_views',
            'views_by_day' => [
                '*' => ['date', 'count'],
            ],
            'views_by_country' => [
                '*' => ['country_code', 'count'],
            ],
        ]);

        // Verify total views
        $this->assertEquals(4, $response->json('total_views'));

        // Verify views_by_day has entries
        $this->assertNotEmpty($response->json('views_by_day'));

        // Verify views_by_country has entries
        $viewsByCountry = $response->json('views_by_country');
        $this->assertNotEmpty($viewsByCountry);

        // FR should have the most views (2)
        $frViews = collect($viewsByCountry)->firstWhere('country_code', 'FR');
        $this->assertEquals(2, $frViews['count']);
    }

    #[Test]
    public function get_detailed_analytics_returns_zero_views_for_post_without_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'no-visits']);

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();

        $this->assertEquals(0, $response->json('total_views'));
        $this->assertEmpty($response->json('views_by_day'));
        $this->assertEmpty($response->json('views_by_country'));
    }

    #[Test]
    public function get_detailed_analytics_excludes_bot_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Real user visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), isBotByUserAgent: false);
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), isBotByUserAgent: false);

        // Bot visits (should be excluded)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), isBotByUserAgent: true);
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'GB'), isBotByFrequency: true);

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();
        // Should only count the 2 non-bot visits
        $this->assertEquals(2, $response->json('total_views'));
    }

    #[Test]
    public function get_detailed_analytics_excludes_authenticated_user_visits(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        $authenticatedUser = User::factory()->create();

        // Guest visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), userId: null);
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), userId: null);

        // Authenticated user visits (should be excluded)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), userId: $authenticatedUser->id);

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();
        // Should only count the 2 guest visits
        $this->assertEquals(2, $response->json('total_views'));
    }

    #[Test]
    public function get_detailed_analytics_filters_by_date_from(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Old visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), createdAt: now()->subDays(5));

        // Recent visits
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), createdAt: now()->subDays(2));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'GB'), createdAt: now()->subDay());

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_from' => now()->subDays(3)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits from the last 3 days
        $this->assertEquals(2, $response->json('total_views'));
    }

    #[Test]
    public function get_detailed_analytics_filters_by_date_to(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Old visits (should be included)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), createdAt: now()->subDays(5));

        // Recent visits (should be excluded)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), createdAt: now()->subDay());
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'GB'), createdAt: now());

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_to' => now()->subDays(4)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits up to 4 days ago
        $this->assertEquals(2, $response->json('total_views'));
    }

    #[Test]
    public function get_detailed_analytics_filters_by_date_range(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Before range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), createdAt: now()->subDays(10));

        // Within range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), createdAt: now()->subDays(7));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'GB'), createdAt: now()->subDays(3));

        // After range
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.5', 'IT'), createdAt: now()->subDay());

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
            'date_from' => now()->subDays(8)->format('Y-m-d'),
            'date_to' => now()->subDays(2)->format('Y-m-d'),
        ]));

        $response->assertOk();
        // Should only count visits within the date range
        $this->assertEquals(3, $response->json('total_views'));
    }

    #[Test]
    public function get_detailed_analytics_views_by_day_are_sorted_chronologically(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // Create visits on different days (in random order)
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'), createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'US'), createdAt: now()->subDays(2));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'DE'), createdAt: now()->subDays(8));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'GB'), createdAt: now()->subDay());

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();

        $viewsByDay = $response->json('views_by_day');

        // Verify dates are in ascending order
        $dates = collect($viewsByDay)->pluck('date')->toArray();
        $sortedDates = collect($dates)->sort()->values()->toArray();
        $this->assertEquals($sortedDates, $dates, 'Dates should be sorted chronologically');
    }

    #[Test]
    public function get_detailed_analytics_views_by_country_are_sorted_by_count_descending(): void
    {
        $this->actingAs($this->user);

        $blogPost = BlogPost::factory()->create(['slug' => 'test-post']);
        $url = $this->createUrl(route('public.blog.post', ['slug' => $blogPost->slug]));

        // FR: 3 visits, US: 2 visits, DE: 1 visit
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.1', 'FR'));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.2', 'FR'));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.3', 'FR'));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.4', 'US'));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.5', 'US'));
        $this->createLoggedRequest($url, $this->createIpAddress('192.168.1.6', 'DE'));

        $response = $this->getJson(route('dashboard.api.blog-posts.analytics', [
            'id' => $blogPost->id,
        ]));

        $response->assertOk();

        $viewsByCountry = $response->json('views_by_country');

        // Verify countries are sorted by count (descending)
        $counts = collect($viewsByCountry)->pluck('count')->toArray();
        $sortedCounts = collect($counts)->sortDesc()->values()->toArray();
        $this->assertEquals($sortedCounts, $counts, 'Countries should be sorted by count (descending)');

        // Verify FR is first with 3 visits
        $this->assertEquals('FR', $viewsByCountry[0]['country_code']);
        $this->assertEquals(3, $viewsByCountry[0]['count']);
    }
}
