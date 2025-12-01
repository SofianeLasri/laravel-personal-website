<?php

namespace Tests\Feature\Services;

use App\Models\IpAddressMetadata;
use App\Models\User;
use App\Models\UserAgentMetadata;
use App\Services\Analytics\VisitStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use SlProjects\LaravelRequestLogger\app\Models\MimeType;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

#[CoversClass(VisitStatsService::class)]
class VisitStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private VisitStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VisitStatsService::class);
    }

    // ===== countUniqueVisits() Tests =====

    #[Test]
    public function it_counts_unique_visits_for_single_url(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        // Create 3 visits from 2 unique IPs
        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        $this->createLoggedRequest($url, $ip1);
        $this->createLoggedRequest($url, $ip1); // Same IP, should not be counted twice
        $this->createLoggedRequest($url, $ip2);

        $count = $this->service->countUniqueVisits($url->url);

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_counts_unique_visits_within_date_range(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');
        $ip3 = $this->createIpAddress('192.168.1.3');

        // Create visits at different dates
        $this->createLoggedRequest($url, $ip1, createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $ip2, createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $ip3, createdAt: now()->subDays(2));

        $count = $this->service->countUniqueVisits(
            $url->url,
            now()->subDays(6)->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        $this->assertEquals(2, $count); // Only ip2 and ip3
    }

    #[Test]
    public function it_excludes_bots_from_unique_visits(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        // Normal visit
        $this->createLoggedRequest($url, $ip1);

        // Bot visit (detected by frequency)
        $this->createLoggedRequest($url, $ip2, isBotByFrequency: true);

        $count = $this->service->countUniqueVisits($url->url);

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_excludes_authenticated_users_from_unique_visits(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        $user = User::factory()->create();
        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        // Visit from authenticated user
        $this->createLoggedRequest($url, $ip1, userId: $user->id);

        // Visit from non-authenticated user
        $this->createLoggedRequest($url, $ip2);

        $count = $this->service->countUniqueVisits($url->url);

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_returns_zero_for_url_with_no_visits(): void
    {
        $url = $this->createUrl(config('app.url').'/non-existent-page');

        $count = $this->service->countUniqueVisits($url->url);

        $this->assertEquals(0, $count);
    }

    // ===== countUniqueVisitsForMultipleUrls() Tests =====

    #[Test]
    public function it_counts_unique_visits_for_multiple_urls(): void
    {
        $url1 = $this->createUrl(config('app.url').'/page1');
        $url2 = $this->createUrl(config('app.url').'/page2');
        $url3 = $this->createUrl(config('app.url').'/page3');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        // URL 1: 2 unique visits
        $this->createLoggedRequest($url1, $ip1);
        $this->createLoggedRequest($url1, $ip2);

        // URL 2: 1 unique visit
        $this->createLoggedRequest($url2, $ip1);

        // URL 3: 0 visits

        $counts = $this->service->countUniqueVisitsForMultipleUrls([
            $url1->url,
            $url2->url,
            $url3->url,
        ]);

        $this->assertEquals([
            $url1->url => 2,
            $url2->url => 1,
            $url3->url => 0,
        ], $counts);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_urls_array(): void
    {
        $counts = $this->service->countUniqueVisitsForMultipleUrls([]);

        $this->assertEquals([], $counts);
    }

    #[Test]
    public function it_counts_multiple_urls_within_date_range(): void
    {
        $url1 = $this->createUrl(config('app.url').'/page1');
        $url2 = $this->createUrl(config('app.url').'/page2');

        $ip1 = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url1, $ip1, createdAt: now()->subDays(10));
        $this->createLoggedRequest($url2, $ip1, createdAt: now()->subDays(2));

        $counts = $this->service->countUniqueVisitsForMultipleUrls(
            [$url1->url, $url2->url],
            now()->subDays(5)->format('Y-m-d'),
            now()->format('Y-m-d')
        );

        $this->assertEquals([
            $url1->url => 0,
            $url2->url => 1,
        ], $counts);
    }

    // ===== getUniqueVisits() Tests =====

    #[Test]
    public function it_gets_unique_visits_with_full_details(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');
        $refererUrl = $this->createUrl('https://google.com');

        $ip = $this->createIpAddress('192.168.1.1');
        $ipMetadata = IpAddressMetadata::factory()->create([
            'ip_address_id' => $ip->id,
            'country_code' => 'FR',
        ]);

        $this->createLoggedRequest($url, $ip, refererUrl: $refererUrl);

        $visits = $this->service->getUniqueVisits();

        $this->assertInstanceOf(Collection::class, $visits);
        $this->assertCount(1, $visits);

        $visit = $visits->first();
        $this->assertEquals($url->url, $visit->url);
        $this->assertEquals('FR', $visit->country_code);
        $this->assertEquals($refererUrl->url, $visit->referer_url);
    }

    #[Test]
    public function it_filters_visits_by_url_pattern(): void
    {
        $url1 = $this->createUrl(config('app.url').'/blog/post-1');
        $url2 = $this->createUrl(config('app.url').'/blog/post-2');
        $url3 = $this->createUrl(config('app.url').'/about');

        $ip = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url1, $ip);
        $this->createLoggedRequest($url2, $ip);
        $this->createLoggedRequest($url3, $ip);

        $visits = $this->service->getUniqueVisits([
            'url_pattern' => config('app.url').'/blog%',
        ]);

        $this->assertCount(2, $visits);
    }

    #[Test]
    public function it_filters_visits_by_excluded_urls(): void
    {
        $url1 = $this->createUrl(config('app.url').'/page1');
        $url2 = $this->createUrl(config('app.url').'/page2');
        $url3 = $this->createUrl(config('app.url').'/page3');

        $ip = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url1, $ip);
        $this->createLoggedRequest($url2, $ip);
        $this->createLoggedRequest($url3, $ip);

        $visits = $this->service->getUniqueVisits([
            'excluded_urls' => [$url2->url, $url3->url],
        ]);

        $this->assertCount(1, $visits);
        $this->assertEquals($url1->url, $visits->first()->url);
    }

    #[Test]
    public function it_filters_visits_by_date_range(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');
        $ip = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(1));

        $visits = $this->service->getUniqueVisits([
            'date_from' => now()->subDays(6)->format('Y-m-d'),
            'date_to' => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->assertCount(1, $visits);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_visits_match_filters(): void
    {
        $visits = $this->service->getUniqueVisits([
            'url_pattern' => config('app.url').'/non-existent%',
        ]);

        $this->assertInstanceOf(Collection::class, $visits);
        $this->assertCount(0, $visits);
    }

    // ===== getVisitsGroupedByDay() Tests =====

    #[Test]
    public function it_groups_visits_by_day(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');
        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        $date1 = now()->subDays(5);
        $date2 = now()->subDays(3);

        $this->createLoggedRequest($url, $ip1, createdAt: $date1);
        $this->createLoggedRequest($url, $ip2, createdAt: $date1);
        $this->createLoggedRequest($url, $ip1, createdAt: $date2);

        $grouped = $this->service->getVisitsGroupedByDay();

        $this->assertInstanceOf(Collection::class, $grouped);
        $this->assertCount(2, $grouped);

        $day1 = $grouped->firstWhere('date', $date1->format('Y-m-d'));
        $this->assertEquals(2, $day1['count']);

        $day2 = $grouped->firstWhere('date', $date2->format('Y-m-d'));
        $this->assertEquals(1, $day2['count']);
    }

    #[Test]
    public function it_sorts_grouped_days_chronologically(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');
        $ip = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(10));
        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(5));
        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(1));

        $grouped = $this->service->getVisitsGroupedByDay();

        $dates = $grouped->pluck('date')->toArray();
        $sortedDates = $grouped->pluck('date')->sort()->values()->toArray();

        $this->assertEquals($sortedDates, $dates);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_visits_for_grouped_by_day(): void
    {
        $grouped = $this->service->getVisitsGroupedByDay();

        $this->assertInstanceOf(Collection::class, $grouped);
        $this->assertCount(0, $grouped);
    }

    // ===== getVisitsGroupedByCountry() Tests =====

    #[Test]
    public function it_groups_visits_by_country(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        $ipFR1 = $this->createIpAddress('192.168.1.1');
        $ipFR2 = $this->createIpAddress('192.168.1.2');
        $ipUS = $this->createIpAddress('192.168.1.3');

        IpAddressMetadata::factory()->create([
            'ip_address_id' => $ipFR1->id,
            'country_code' => 'FR',
        ]);
        IpAddressMetadata::factory()->create([
            'ip_address_id' => $ipFR2->id,
            'country_code' => 'FR',
        ]);
        IpAddressMetadata::factory()->create([
            'ip_address_id' => $ipUS->id,
            'country_code' => 'US',
        ]);

        $this->createLoggedRequest($url, $ipFR1);
        $this->createLoggedRequest($url, $ipFR2);
        $this->createLoggedRequest($url, $ipUS);

        $grouped = $this->service->getVisitsGroupedByCountry();

        $this->assertInstanceOf(Collection::class, $grouped);
        $this->assertCount(2, $grouped);

        $fr = $grouped->firstWhere('country_code', 'FR');
        $this->assertEquals(2, $fr['count']);

        $us = $grouped->firstWhere('country_code', 'US');
        $this->assertEquals(1, $us['count']);
    }

    #[Test]
    public function it_sorts_countries_by_count_descending(): void
    {
        $url = $this->createUrl(config('app.url').'/test-page');

        $ipFR = $this->createIpAddress('192.168.1.1');
        $ipUS1 = $this->createIpAddress('192.168.1.2');
        $ipUS2 = $this->createIpAddress('192.168.1.3');

        IpAddressMetadata::factory()->create(['ip_address_id' => $ipFR->id, 'country_code' => 'FR']);
        IpAddressMetadata::factory()->create(['ip_address_id' => $ipUS1->id, 'country_code' => 'US']);
        IpAddressMetadata::factory()->create(['ip_address_id' => $ipUS2->id, 'country_code' => 'US']);

        $this->createLoggedRequest($url, $ipFR);
        $this->createLoggedRequest($url, $ipUS1);
        $this->createLoggedRequest($url, $ipUS2);

        $grouped = $this->service->getVisitsGroupedByCountry();

        $this->assertEquals('US', $grouped->first()['country_code']);
        $this->assertEquals('FR', $grouped->last()['country_code']);
    }

    // ===== getMostVisitedPages() Tests =====

    #[Test]
    public function it_gets_most_visited_pages(): void
    {
        $url1 = $this->createUrl(config('app.url').'/page1');
        $url2 = $this->createUrl(config('app.url').'/page2');
        $url3 = $this->createUrl(config('app.url').'/page3');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');
        $ip3 = $this->createIpAddress('192.168.1.3');

        // URL1: 3 visits
        $this->createLoggedRequest($url1, $ip1);
        $this->createLoggedRequest($url1, $ip2);
        $this->createLoggedRequest($url1, $ip3);

        // URL2: 2 visits
        $this->createLoggedRequest($url2, $ip1);
        $this->createLoggedRequest($url2, $ip2);

        // URL3: 1 visit
        $this->createLoggedRequest($url3, $ip1);

        $pages = $this->service->getMostVisitedPages();

        $this->assertInstanceOf(Collection::class, $pages);
        $this->assertCount(3, $pages);

        $this->assertEquals($url1->url, $pages->first()['url']);
        $this->assertEquals(3, $pages->first()['count']);
    }

    #[Test]
    public function it_limits_most_visited_pages_results(): void
    {
        $urls = [];
        $ip = $this->createIpAddress('192.168.1.1');

        for ($i = 1; $i <= 15; $i++) {
            $urls[] = $this->createUrl(config('app.url')."/page{$i}");
            $this->createLoggedRequest($urls[$i - 1], $ip);
        }

        $pages = $this->service->getMostVisitedPages([], 5);

        $this->assertCount(5, $pages);
    }

    // ===== getBestReferrers() Tests =====

    #[Test]
    public function it_gets_best_referrers(): void
    {
        $url = $this->createUrl(config('app.url').'/page');
        $referer1 = $this->createUrl('https://google.com');
        $referer2 = $this->createUrl('https://facebook.com');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');
        $ip3 = $this->createIpAddress('192.168.1.3');

        $this->createLoggedRequest($url, $ip1, refererUrl: $referer1);
        $this->createLoggedRequest($url, $ip2, refererUrl: $referer1);
        $this->createLoggedRequest($url, $ip3, refererUrl: $referer2);

        $referrers = $this->service->getBestReferrers();

        $this->assertInstanceOf(Collection::class, $referrers);
        $this->assertCount(2, $referrers);

        $this->assertEquals($referer1->url, $referrers->first()['url']);
        $this->assertEquals(2, $referrers->first()['count']);
    }

    // ===== getBestOrigins() Tests =====

    #[Test]
    public function it_gets_best_origins(): void
    {
        $url = $this->createUrl(config('app.url').'/page');
        $origin1 = $this->createUrl('https://app1.example.com');
        $origin2 = $this->createUrl('https://app2.example.com');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');

        $this->createLoggedRequest($url, $ip1, originUrl: $origin1);
        $this->createLoggedRequest($url, $ip2, originUrl: $origin2);

        $origins = $this->service->getBestOrigins();

        $this->assertInstanceOf(Collection::class, $origins);
        $this->assertCount(2, $origins);
    }

    // ===== getTotalVisitsByPeriods() Tests =====

    #[Test]
    public function it_calculates_total_visits_by_periods(): void
    {
        $url = $this->createUrl(config('app.url').'/page');

        $ip1 = $this->createIpAddress('192.168.1.1');
        $ip2 = $this->createIpAddress('192.168.1.2');
        $ip3 = $this->createIpAddress('192.168.1.3');
        $ip4 = $this->createIpAddress('192.168.1.4');

        $this->createLoggedRequest($url, $ip1, createdAt: now()->subHours(2)); // Past 24h
        $this->createLoggedRequest($url, $ip2, createdAt: now()->subDays(3)); // Past 7d
        $this->createLoggedRequest($url, $ip3, createdAt: now()->subDays(10)); // Past 30d
        $this->createLoggedRequest($url, $ip4, createdAt: now()->subDays(50)); // All time only

        $periods = $this->service->getTotalVisitsByPeriods();

        $this->assertIsArray($periods);
        $this->assertArrayHasKey('past_24h', $periods);
        $this->assertArrayHasKey('past_7d', $periods);
        $this->assertArrayHasKey('past_30d', $periods);
        $this->assertArrayHasKey('all_time', $periods);

        $this->assertEquals(1, $periods['past_24h']);
        $this->assertEquals(2, $periods['past_7d']);
        $this->assertEquals(3, $periods['past_30d']);
        $this->assertEquals(4, $periods['all_time']);
    }

    #[Test]
    public function it_returns_zero_counts_when_no_visits(): void
    {
        $periods = $this->service->getTotalVisitsByPeriods();

        $this->assertEquals(0, $periods['past_24h']);
        $this->assertEquals(0, $periods['past_7d']);
        $this->assertEquals(0, $periods['past_30d']);
        $this->assertEquals(0, $periods['all_time']);
    }

    // ===== getAvailablePeriods() Tests =====

    #[Test]
    public function it_returns_available_periods_without_visits(): void
    {
        $periods = $this->service->getAvailablePeriods();

        $this->assertIsArray($periods);
        $this->assertArrayHasKey(now()->format('Y-m-d'), $periods);
        $this->assertArrayHasKey(now()->subDay()->format('Y-m-d'), $periods);
    }

    #[Test]
    public function it_adds_earliest_date_when_visits_provided(): void
    {
        $url = $this->createUrl(config('app.url').'/page');
        $ip = $this->createIpAddress('192.168.1.1');

        $this->createLoggedRequest($url, $ip, createdAt: now()->subDays(100));

        $visits = $this->service->getUniqueVisits();
        $periods = $this->service->getAvailablePeriods($visits);

        $this->assertArrayHasKey(now()->subDays(100)->format('Y-m-d'), $periods);
        $this->assertEquals('Depuis le début', $periods[now()->subDays(100)->format('Y-m-d')]);
    }

    // ===== Helper Methods =====

    private function createUrl(string $url): Url
    {
        return Url::firstOrCreate(['url' => $url]);
    }

    private function createIpAddress(string $ip): IpAddress
    {
        return IpAddress::firstOrCreate(['ip' => $ip]);
    }

    private function createLoggedRequest(
        Url $url,
        IpAddress $ipAddress,
        ?int $userId = null,
        bool $isBotByFrequency = false,
        bool $isBotByUserAgent = false,
        bool $isBotByParameters = false,
        ?Url $refererUrl = null,
        ?Url $originUrl = null,
        ?\DateTimeInterface $createdAt = null
    ): LoggedRequest {
        $userAgent = UserAgent::firstOrCreate(['user_agent' => 'Mozilla/5.0']);
        $mimeType = MimeType::firstOrCreate(['mime_type' => 'text/html']);

        // Create user agent metadata (for is_bot flag)
        UserAgentMetadata::firstOrCreate(
            ['user_agent_id' => $userAgent->id],
            ['is_bot' => false]
        );

        // Use DB::table()->insert() instead of Model::create() to bypass mass assignment protection
        $id = DB::table('logged_requests')->insertGetId([
            'url_id' => $url->id,
            'ip_address_id' => $ipAddress->id,
            'user_agent_id' => $userAgent->id,
            'mime_type_id' => $mimeType->id,
            'referer_url_id' => $refererUrl?->id,
            'origin_url_id' => $originUrl?->id,
            'status_code' => 200,
            'method' => 'GET',
            'user_id' => $userId,
            'is_bot_by_frequency' => $isBotByFrequency ? 1 : 0,
            'is_bot_by_user_agent' => $isBotByUserAgent ? 1 : 0,
            'is_bot_by_parameters' => $isBotByParameters ? 1 : 0,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return LoggedRequest::find($id);
    }
}
