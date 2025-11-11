<?php

namespace App\Services\Analytics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

/**
 * Service for building filtered queries on logged_requests table.
 * Provides reusable methods to apply common filters (bots, authenticated users, dates, URLs, etc.)
 *
 * This service centralizes all filtering logic used across:
 * - Dashboard statistics (HomeController)
 * - Request logs listing (RequestLogController)
 * - Blog post views analytics (BlogPostAnalyticsController)
 * - Future: Creation views, comment analytics, etc.
 *
 * @phpstan-type FilterArray array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}
 */
class FilteredRequestQueryService
{
    /**
     * Build base query with all necessary joins for filtering and analysis.
     * Includes joins for: IP addresses, user agents, URLs, mime types, and metadata tables.
     *
     * @return Builder<LoggedRequest>
     */
    public function buildBaseQuery(): Builder
    {
        return LoggedRequest::query()
            ->leftJoin('ip_addresses', 'logged_requests.ip_address_id', '=', 'ip_addresses.id')
            ->leftJoin('user_agents', 'logged_requests.user_agent_id', '=', 'user_agents.id')
            ->leftJoin('urls', 'logged_requests.url_id', '=', 'urls.id')
            ->leftJoin('urls as referer_urls', 'logged_requests.referer_url_id', '=', 'referer_urls.id')
            ->leftJoin('urls as origin_urls', 'logged_requests.origin_url_id', '=', 'origin_urls.id')
            ->leftJoin('mime_types', 'logged_requests.mime_type_id', '=', 'mime_types.id')
            ->leftJoin('ip_address_metadata', 'ip_addresses.id', '=', 'ip_address_metadata.ip_address_id')
            ->leftJoin('user_agent_metadata', 'user_agents.id', '=', 'user_agent_metadata.user_agent_id');
    }

    /**
     * Apply bot detection filters to exclude/include bot requests.
     *
     * Filters based on three detection methods:
     * - Frequency analysis (high request rate)
     * - User agent analysis (known bots, suspicious patterns)
     * - URL parameter analysis (random strings, attack patterns)
     * - Legacy is_bot flag from user_agent_metadata
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  bool  $excludeBots  True to exclude bots (default), false to only include bots, null for no filtering
     * @return Builder<LoggedRequest>
     */
    public function applyBotFilters(Builder $query, ?bool $excludeBots = true): Builder
    {
        if ($excludeBots === null) {
            return $query;
        }

        return $query->where(function ($q) use ($excludeBots) {
            if ($excludeBots) {
                // Exclude bots: all detection flags must be false
                $q->where(function ($subQ) {
                    $subQ->whereNull('user_agent_metadata.is_bot')
                        ->orWhere('user_agent_metadata.is_bot', false);
                })
                    // @phpstan-ignore argument.type
                    ->where('logged_requests.is_bot_by_frequency', false)
                    // @phpstan-ignore argument.type
                    ->where('logged_requests.is_bot_by_user_agent', false)
                    // @phpstan-ignore argument.type
                    ->where('logged_requests.is_bot_by_parameters', false);
            } else {
                // Include only bots: at least one detection flag must be true
                $q->where('user_agent_metadata.is_bot', true)
                    // @phpstan-ignore argument.type
                    ->orWhere('logged_requests.is_bot_by_frequency', true)
                    // @phpstan-ignore argument.type
                    ->orWhere('logged_requests.is_bot_by_user_agent', true)
                    // @phpstan-ignore argument.type
                    ->orWhere('logged_requests.is_bot_by_parameters', true);
            }
        });
    }

    /**
     * Apply filters to exclude authenticated users and their IP addresses.
     *
     * Two-level filtering:
     * 1. Direct: Exclude requests where user_id is not null (logged-in user made request)
     * 2. Indirect: Exclude ALL requests from IPs that have EVER been used by authenticated users
     *    This prevents counting pre-auth visits from the same IP as "unique visitors"
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  bool  $excludeAuthUsers  True to exclude authenticated users (default), false to disable filtering
     * @return Builder<LoggedRequest>
     */
    public function applyAuthenticatedUserFilters(Builder $query, bool $excludeAuthUsers = true): Builder
    {
        if (! $excludeAuthUsers) {
            return $query;
        }

        return $query
            // Exclude requests made by authenticated users
            ->whereNull('logged_requests.user_id')
            // Exclude ALL requests from IPs that have been used by authenticated users
            ->whereNotIn('ip_addresses.id', function ($subquery) {
                $subquery->select('ip_addresses.id')
                    ->from('ip_addresses')
                    ->join('logged_requests as lr', 'lr.ip_address_id', '=', 'ip_addresses.id')
                    ->whereNotNull('lr.user_id');
            });
    }

    /**
     * Apply status code filter (e.g., successful responses only).
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  array<int, int>  $statusCodes  Array of HTTP status codes to include (default: [200, 304])
     * @return Builder<LoggedRequest>
     */
    public function applyStatusCodeFilter(Builder $query, array $statusCodes = [200, 304]): Builder
    {
        if (empty($statusCodes)) {
            return $query;
        }

        return $query->whereIn('logged_requests.status_code', $statusCodes);
    }

    /**
     * Apply date range filter.
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  string|null  $dateFrom  Start date (YYYY-MM-DD format)
     * @param  string|null  $dateTo  End date (YYYY-MM-DD format)
     * @return Builder<LoggedRequest>
     */
    public function applyDateRangeFilter(Builder $query, ?string $dateFrom = null, ?string $dateTo = null): Builder
    {
        if ($dateFrom) {
            // @phpstan-ignore argument.type (Column exists in table)
            $query->where('logged_requests.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            // @phpstan-ignore argument.type (Column exists in table)
            $query->where('logged_requests.created_at', '<=', $dateTo.' 23:59:59');
        }

        return $query;
    }

    /**
     * Apply URL filter (include specific URL pattern or exclude URLs).
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  string|null  $urlPattern  URL pattern to match (supports LIKE wildcards)
     * @param  array<int, string>|null  $excludedUrls  Array of URLs to exclude (exact match)
     * @return Builder<LoggedRequest>
     */
    public function applyUrlFilter(Builder $query, ?string $urlPattern = null, ?array $excludedUrls = null): Builder
    {
        if ($urlPattern) {
            $query->where('urls.url', 'like', $urlPattern);
        }

        if (! empty($excludedUrls)) {
            $query->whereNotIn('urls.url', $excludedUrls);
        }

        return $query;
    }

    /**
     * Apply IP address filters (include or exclude specific IPs).
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  array<int, string>|null  $includeIps  Array of IP addresses to include (exact match)
     * @param  array<int, string>|null  $excludeIps  Array of IP addresses to exclude (exact match)
     * @return Builder<LoggedRequest>
     */
    public function applyIpFilters(Builder $query, ?array $includeIps = null, ?array $excludeIps = null): Builder
    {
        if (! empty($includeIps)) {
            $query->whereIn('ip_addresses.ip', $includeIps);
        }

        if (! empty($excludeIps)) {
            $query->whereNotIn('ip_addresses.ip', $excludeIps);
        }

        return $query;
    }

    /**
     * Apply user agent filters (include or exclude based on user agent strings).
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  array<int, string>|null  $includeUserAgents  Array of user agent patterns to include (partial match)
     * @param  array<int, string>|null  $excludeUserAgents  Array of user agent patterns to exclude (partial match)
     * @return Builder<LoggedRequest>
     */
    public function applyUserAgentFilters(Builder $query, ?array $includeUserAgents = null, ?array $excludeUserAgents = null): Builder
    {
        if (! empty($includeUserAgents)) {
            $query->where(function ($q) use ($includeUserAgents) {
                foreach ($includeUserAgents as $userAgent) {
                    $q->orWhere('user_agents.user_agent', 'like', "%{$userAgent}%");
                }
            });
        }

        if (! empty($excludeUserAgents)) {
            foreach ($excludeUserAgents as $userAgent) {
                $query->where('user_agents.user_agent', 'not like', "%{$userAgent}%");
            }
        }

        return $query;
    }

    /**
     * Apply search filter across multiple fields (IP, URL, user agent, method, status code).
     *
     * @param  Builder<LoggedRequest>  $query  The query builder instance
     * @param  string|null  $search  Search term
     * @return Builder<LoggedRequest>
     */
    public function applySearchFilter(Builder $query, ?string $search = null): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('ip_addresses.ip', 'like', "%{$search}%")
                ->orWhere('urls.url', 'like', "%{$search}%")
                ->orWhere('user_agents.user_agent', 'like', "%{$search}%")
                ->orWhere('logged_requests.method', 'like', "%{$search}%")
                ->orWhere('logged_requests.status_code', 'like', "%{$search}%");
        });
    }

    /**
     * Build a query to count unique visitors (unique IPs).
     * Applies standard filters for clean analytics: no bots, no authenticated users.
     *
     * @param  string  $url  The URL to count visits for (exact match)
     * @param  string|null  $dateFrom  Optional start date
     * @param  string|null  $dateTo  Optional end date
     * @return Builder<LoggedRequest> Query ready to execute with ->count(DB::raw('DISTINCT ip_addresses.id'))
     */
    public function buildUniqueVisitorsQuery(string $url, ?string $dateFrom = null, ?string $dateTo = null): Builder
    {
        $query = $this->buildBaseQuery();

        // @phpstan-ignore argument.type (Column exists in joined table)
        $query->where('urls.url', $url);

        $this->applyBotFilters($query);
        $this->applyAuthenticatedUserFilters($query);
        $this->applyStatusCodeFilter($query);
        $this->applyDateRangeFilter($query, $dateFrom, $dateTo);

        return $query;
    }
}
