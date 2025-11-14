<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating visit statistics and analytics.
 * Provides high-level methods for counting unique visitors, grouping visits, and generating reports.
 *
 * This service uses FilteredRequestQueryService to build clean queries (no bots, no auth users).
 * All methods return aggregated data suitable for display in dashboards and analytics views.
 */
class VisitStatsService
{
    public function __construct(
        protected FilteredRequestQueryService $queryService
    ) {}

    /**
     * Count unique visitors (unique IPs) for a specific URL.
     * Applies standard filters: no bots, no authenticated users, successful responses only.
     *
     * @param  string  $url  The exact URL to count visits for
     * @param  string|null  $dateFrom  Optional start date (YYYY-MM-DD)
     * @param  string|null  $dateTo  Optional end date (YYYY-MM-DD)
     * @return int Number of unique IP addresses that visited this URL
     */
    public function countUniqueVisits(string $url, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        return $this->queryService
            ->buildUniqueVisitorsQuery($url, $dateFrom, $dateTo)
            ->count(DB::raw('DISTINCT ip_addresses.id'));
    }

    /**
     * Count unique visitors for multiple URLs at once.
     * More efficient than calling countUniqueVisits() in a loop.
     *
     * @param  array<int, string>  $urls  Array of URLs to count visits for ['url1', 'url2', ...]
     * @param  string|null  $dateFrom  Optional start date
     * @param  string|null  $dateTo  Optional end date
     * @return array<string, int> Associative array mapping URL => count ['url1' => 42, 'url2' => 18, ...]
     */
    public function countUniqueVisitsForMultipleUrls(array $urls, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (empty($urls)) {
            return [];
        }

        $query = $this->queryService->buildBaseQuery()
            ->select([
                'urls.url',
                DB::raw('COUNT(DISTINCT ip_addresses.id) as unique_visits'),
            ])
            ->whereIn('urls.url', $urls);

        $this->queryService->applyBotFilters($query);
        $this->queryService->applyAuthenticatedUserFilters($query);
        $this->queryService->applyStatusCodeFilter($query);
        $this->queryService->applyDateRangeFilter($query, $dateFrom, $dateTo);

        $results = $query->groupBy('urls.url')->get();

        // Build associative array with all URLs (including those with 0 visits)
        $counts = array_fill_keys($urls, 0);
        foreach ($results as $result) {
            // @phpstan-ignore property.notFound,offsetAccess.invalidOffset (Dynamic properties from query)
            $counts[$result->url] = (int) $result->unique_visits;
        }

        return $counts;
    }

    /**
     * Get all unique visits with full details (used for advanced analytics).
     * Returns a collection with all visit records for further processing.
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Array of filter options:
     *                                                                                                                          - 'url_pattern' (string): URL pattern to match
     *                                                                                                                          - 'excluded_urls' (array): URLs to exclude
     *                                                                                                                          - 'date_from' (string): Start date
     *                                                                                                                          - 'date_to' (string): End date
     * @return Collection<int, mixed> Collection of visit records
     */
    public function getUniqueVisits(array $filters = []): Collection
    {
        $query = $this->queryService->buildBaseQuery()
            ->select([
                'logged_requests.url_id',
                'logged_requests.referer_url_id',
                'logged_requests.origin_url_id',
                'logged_requests.ip_address_id',
                'ip_address_metadata.country_code',
                'logged_requests.created_at',
                'urls.url',
                'referer_urls.url as referer_url',
                'origin_urls.url as origin_url',
            ])
            ->distinct(['logged_requests.url_id', 'logged_requests.ip_address_id']);

        // Apply standard filters
        $this->queryService->applyBotFilters($query);
        $this->queryService->applyAuthenticatedUserFilters($query);
        $this->queryService->applyStatusCodeFilter($query);

        // Apply custom filters
        if (! empty($filters['url_pattern'])) {
            $this->queryService->applyUrlFilter($query, $filters['url_pattern'], $filters['excluded_urls'] ?? null);
        } elseif (! empty($filters['excluded_urls'])) {
            $this->queryService->applyUrlFilter($query, null, $filters['excluded_urls']);
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $this->queryService->applyDateRangeFilter($query, $filters['date_from'] ?? null, $filters['date_to'] ?? null);
        }

        return $query->get();
    }

    /**
     * Get visits grouped by day for charting and trend analysis.
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return Collection<int, array{date: string, count: int}> Collection of ['date' => 'YYYY-MM-DD', 'count' => int]
     */
    public function getVisitsGroupedByDay(array $filters = [], ?Collection $visits = null): Collection
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);

        $startDate = $filters['date_from'] ?? null;
        $endDate = $filters['date_to'] ?? null;

        if ($startDate) {
            $visits = $visits->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $visits = $visits->where('created_at', '<=', $endDate);
        }

        return $visits
            ->groupBy(fn ($visit) => Carbon::parse($visit->created_at)->format('Y-m-d'))
            ->map(fn ($group) => [
                'date' => Carbon::parse($group->first()->created_at)->format('Y-m-d'),
                'count' => $group->count(),
            ])
            ->values()
            ->sortBy('date')
            ->values();
    }

    /**
     * Get visits grouped by country for geographic analysis.
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return Collection<int, mixed> Collection of ['country_code' => 'FR', 'count' => int]
     */
    public function getVisitsGroupedByCountry(array $filters = [], ?Collection $visits = null): Collection
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);

        $startDate = $filters['date_from'] ?? null;
        $endDate = $filters['date_to'] ?? null;

        if ($startDate) {
            $visits = $visits->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $visits = $visits->where('created_at', '<=', $endDate);
        }

        return $visits
            ->groupBy('country_code')
            ->map(fn ($group, $country) => [
                'country_code' => $country,
                'count' => $group->count(),
            ])
            ->values()
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Get most visited pages.
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  int  $limit  Number of results to return
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return Collection<int, mixed> Collection of ['url' => string, 'count' => int]
     */
    public function getMostVisitedPages(array $filters = [], int $limit = 10, ?Collection $visits = null): Collection
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);

        $startDate = $filters['date_from'] ?? null;
        $endDate = $filters['date_to'] ?? null;

        if ($startDate) {
            $visits = $visits->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $visits = $visits->where('created_at', '<=', $endDate);
        }

        return $visits
            ->groupBy('url')
            ->map(fn ($group, $url) => [
                'url' => $url,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Get best referrers (sources of traffic).
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  int  $limit  Number of results to return
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return Collection<int, mixed> Collection of ['url' => string, 'count' => int]
     */
    public function getBestReferrers(array $filters = [], int $limit = 10, ?Collection $visits = null): Collection
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);

        $startDate = $filters['date_from'] ?? null;
        $endDate = $filters['date_to'] ?? null;

        if ($startDate) {
            $visits = $visits->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $visits = $visits->where('created_at', '<=', $endDate);
        }

        return $visits
            ->groupBy('referer_url')
            ->map(fn ($group, $url) => [
                'url' => $url,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Get best origins (HTTP Origin header sources).
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  int  $limit  Number of results to return
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return Collection<int, mixed> Collection of ['url' => string, 'count' => int]
     */
    public function getBestOrigins(array $filters = [], int $limit = 10, ?Collection $visits = null): Collection
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);

        $startDate = $filters['date_from'] ?? null;
        $endDate = $filters['date_to'] ?? null;

        if ($startDate) {
            $visits = $visits->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $visits = $visits->where('created_at', '<=', $endDate);
        }

        return $visits
            ->groupBy('origin_url')
            ->map(fn ($group, $url) => [
                'url' => $url,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Calculate total visits for different time periods.
     *
     * @param  array{url_pattern?: string, excluded_urls?: array<int, string>, date_from?: string, date_to?: string}  $filters  Same as getUniqueVisits()
     * @param  Collection<int, mixed>|null  $visits  Optional pre-fetched visits collection for performance
     * @return array{past_24h: int, past_7d: int, past_30d: int, all_time: int} Array with keys: 'past_24h', 'past_7d', 'past_30d', 'all_time'
     */
    public function getTotalVisitsByPeriods(array $filters = [], ?Collection $visits = null): array
    {
        $visits = $visits ?? $this->getUniqueVisits($filters);
        $now = now();

        return [
            'past_24h' => $visits->where('created_at', '>=', $now->copy()->subDay())->count(),
            'past_7d' => $visits->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'past_30d' => $visits->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'all_time' => $visits->count(),
        ];
    }

    /**
     * Get available date periods for filtering (used in dashboard selectors).
     *
     * @param  Collection<int, object{created_at: string}>|null  $visits  Optional visits collection to determine earliest date
     * @return array<string, string> Array mapping date => label
     */
    public function getAvailablePeriods(?Collection $visits = null): array
    {
        $periods = [
            now()->format('Y-m-d') => 'Aujourd\'hui',
            now()->subDay()->format('Y-m-d') => 'Hier',
            now()->subDays(7)->format('Y-m-d') => 'Les 7 derniers jours',
            now()->subDays(30)->format('Y-m-d') => 'Les 30 derniers jours',
            now()->startOfMonth()->format('Y-m-d') => 'Ce mois-ci',
            now()->subMonth()->startOfMonth()->format('Y-m-d') => 'Le mois dernier',
        ];

        if ($visits && $visits->isNotEmpty()) {
            $earliestDate = $visits->min('created_at');
            if ($earliestDate) {
                $periods[Carbon::parse($earliestDate)->format('Y-m-d')] = 'Depuis le début';
            }
        }

        return $periods;
    }
}
