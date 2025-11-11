<?php

namespace Tests\Unit\Services\Analytics;

use App\Services\Analytics\FilteredRequestQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;
use Tests\TestCase;

class FilteredRequestQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FilteredRequestQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FilteredRequestQueryService;
    }

    /** @test */
    public function it_builds_base_query_with_all_necessary_joins(): void
    {
        $query = $this->service->buildBaseQuery();

        $this->assertInstanceOf(Builder::class, $query);

        // Check that the query is based on LoggedRequest
        $this->assertInstanceOf(LoggedRequest::class, $query->getModel());

        // Verify joins are present by checking the query SQL contains join clauses
        $sql = $query->toSql();
        $this->assertStringContainsString('left join', strtolower($sql));
    }

    /** @test */
    public function it_applies_bot_filters_to_exclude_bots(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyBotFilters($query, true);

        $sql = $query->toSql();

        // Should have conditions to exclude bots
        $this->assertStringContainsString('is_bot_by_frequency', $sql);
        $this->assertStringContainsString('is_bot_by_user_agent', $sql);
        $this->assertStringContainsString('is_bot_by_parameters', $sql);
    }

    /** @test */
    public function it_applies_bot_filters_to_include_only_bots(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyBotFilters($query, false);

        $sql = $query->toSql();

        // Should have conditions to include only bots
        $this->assertStringContainsString('is_bot_by_frequency', $sql);
        $this->assertStringContainsString('is_bot_by_user_agent', $sql);
        $this->assertStringContainsString('is_bot_by_parameters', $sql);
    }

    /** @test */
    public function it_skips_bot_filters_when_null_is_passed(): void
    {
        $query = $this->service->buildBaseQuery();
        $sqlBefore = $query->toSql();

        $this->service->applyBotFilters($query, null);
        $sqlAfter = $query->toSql();

        // SQL should not change when null is passed
        $this->assertEquals($sqlBefore, $sqlAfter);
    }

    /** @test */
    public function it_applies_authenticated_user_filters(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyAuthenticatedUserFilters($query, true);

        $sql = $query->toSql();

        // Should check for null user_id and exclude IPs used by authenticated users
        $this->assertStringContainsString('user_id', strtolower($sql));
        $this->assertStringContainsString('not in', strtolower($sql));
    }

    /** @test */
    public function it_skips_authenticated_user_filters_when_false(): void
    {
        $query = $this->service->buildBaseQuery();
        $sqlBefore = $query->toSql();

        $this->service->applyAuthenticatedUserFilters($query, false);
        $sqlAfter = $query->toSql();

        // SQL should not change when false is passed
        $this->assertEquals($sqlBefore, $sqlAfter);
    }

    /** @test */
    public function it_applies_status_code_filter(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyStatusCodeFilter($query, [200, 304]);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Should have status_code in where clause
        $this->assertStringContainsString('status_code', strtolower($sql));
        $this->assertStringContainsString('in', strtolower($sql));
        $this->assertContains(200, $bindings);
        $this->assertContains(304, $bindings);
    }

    /** @test */
    public function it_skips_status_code_filter_when_empty_array(): void
    {
        $query = $this->service->buildBaseQuery();
        $sqlBefore = $query->toSql();

        $this->service->applyStatusCodeFilter($query, []);
        $sqlAfter = $query->toSql();

        // SQL should not change when empty array is passed
        $this->assertEquals($sqlBefore, $sqlAfter);
    }

    /** @test */
    public function it_applies_date_range_filter_with_start_date(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyDateRangeFilter($query, '2025-01-01', null);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('created_at', strtolower($sql));
        $this->assertStringContainsString('>=', $sql);
        $this->assertContains('2025-01-01', $bindings);
    }

    /** @test */
    public function it_applies_date_range_filter_with_end_date(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyDateRangeFilter($query, null, '2025-12-31');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('created_at', strtolower($sql));
        $this->assertStringContainsString('<=', $sql);
        // End date should be appended with time
        $this->assertTrue(in_array('2025-12-31 23:59:59', $bindings));
    }

    /** @test */
    public function it_applies_url_filter_with_pattern(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyUrlFilter($query, 'https://example.com%', null);

        $sql = $query->toSql();

        $this->assertStringContainsString('url', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
    }

    /** @test */
    public function it_applies_url_filter_with_exclusions(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyUrlFilter($query, null, ['https://example.com/admin', 'https://example.com/login']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('url', strtolower($sql));
        $this->assertStringContainsString('not in', strtolower($sql));
        $this->assertContains('https://example.com/admin', $bindings);
        $this->assertContains('https://example.com/login', $bindings);
    }

    /** @test */
    public function it_applies_ip_filters_with_inclusions(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyIpFilters($query, ['192.168.1.1', '10.0.0.1'], null);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('in', strtolower($sql));
        $this->assertContains('192.168.1.1', $bindings);
        $this->assertContains('10.0.0.1', $bindings);
    }

    /** @test */
    public function it_applies_ip_filters_with_exclusions(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyIpFilters($query, null, ['192.168.1.1', '10.0.0.1']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('not in', strtolower($sql));
        $this->assertContains('192.168.1.1', $bindings);
        $this->assertContains('10.0.0.1', $bindings);
    }

    /** @test */
    public function it_applies_user_agent_filters_with_inclusions(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyUserAgentFilters($query, ['Chrome', 'Firefox'], null);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('user_agent', strtolower($sql));
        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertContains('%Chrome%', $bindings);
        $this->assertContains('%Firefox%', $bindings);
    }

    /** @test */
    public function it_applies_user_agent_filters_with_exclusions(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applyUserAgentFilters($query, null, ['bot', 'crawler']);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('user_agent', strtolower($sql));
        $this->assertStringContainsString('not like', strtolower($sql));
        $this->assertContains('%bot%', $bindings);
        $this->assertContains('%crawler%', $bindings);
    }

    /** @test */
    public function it_applies_search_filter(): void
    {
        $query = $this->service->buildBaseQuery();
        $this->service->applySearchFilter($query, 'test search');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Should search across multiple fields
        $this->assertStringContainsString('like', strtolower($sql));
        // The search term should appear multiple times (once for each field)
        $searchCount = count(array_filter($bindings, fn ($binding) => $binding === '%test search%'));
        $this->assertGreaterThan(3, $searchCount, 'Search should be applied to multiple fields');
    }

    /** @test */
    public function it_builds_unique_visitors_query(): void
    {
        $url = 'https://example.com/blog/article';
        $query = $this->service->buildUniqueVisitorsQuery($url, '2025-01-01', '2025-12-31');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Should include URL filter
        $this->assertContains($url, $bindings);

        // Should include date filters
        $this->assertContains('2025-01-01', $bindings);
        $this->assertTrue(in_array('2025-12-31 23:59:59', $bindings));

        // Should include bot detection columns
        $this->assertStringContainsString('is_bot_by_frequency', $sql);
        $this->assertStringContainsString('is_bot_by_user_agent', $sql);
        $this->assertStringContainsString('is_bot_by_parameters', $sql);

        // Should include status_code filter
        $this->assertStringContainsString('status_code', strtolower($sql));

        // Should include user_id null check
        $this->assertStringContainsString('user_id', strtolower($sql));
    }
}
