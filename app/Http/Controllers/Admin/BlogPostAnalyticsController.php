<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Analytics\VisitStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for blog post analytics and view statistics.
 * Provides API endpoints for retrieving view counts and analytics data.
 */
class BlogPostAnalyticsController extends Controller
{
    public function __construct(
        protected VisitStatsService $visitStatsService
    ) {}

    /**
     * Get view counts for multiple blog posts.
     * Returns unique visitor count (unique IPs) for each blog post.
     *
     * Query parameters:
     * - ids: array of blog post IDs (required)
     * - date_from: optional start date for counting (YYYY-MM-DD)
     * - date_to: optional end date for counting (YYYY-MM-DD)
     *
     * Response format:
     * {
     *   "views": {
     *     "1": 142,
     *     "2": 87,
     *     "3": 0
     *   }
     * }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getViews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:blog_posts,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $blogPostIds = $validated['ids'];
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        // Fetch blog posts with their slugs
        $blogPosts = BlogPost::whereIn('id', $blogPostIds)
            ->select('id', 'slug')
            ->get()
            ->keyBy('id');

        // Build URLs for each blog post
        $urlMap = [];
        foreach ($blogPosts as $id => $blogPost) {
            $urlMap[$id] = route('public.blog.post', ['slug' => $blogPost->slug]);
        }

        // Get view counts for all URLs at once (more efficient than looping)
        $urls = array_values($urlMap);
        $viewCounts = $this->visitStatsService->countUniqueVisitsForMultipleUrls($urls, $dateFrom, $dateTo);

        // Map results back to blog post IDs
        $views = [];
        foreach ($urlMap as $id => $url) {
            $views[$id] = $viewCounts[$url] ?? 0;
        }

        return response()->json([
            'views' => $views,
        ]);
    }

    /**
     * Get detailed analytics for a single blog post.
     * Returns view count and additional metrics.
     *
     * URL parameters:
     * - id: blog post ID (required)
     *
     * Query parameters:
     * - date_from: optional start date (YYYY-MM-DD)
     * - date_to: optional end date (YYYY-MM-DD)
     *
     * Response format:
     * {
     *   "total_views": 142,
     *   "views_by_day": [
     *     {"date": "2025-11-01", "count": 12},
     *     {"date": "2025-11-02", "count": 8}
     *   ],
     *   "views_by_country": [
     *     {"country_code": "FR", "count": 89},
     *     {"country_code": "US", "count": 34}
     *   ]
     * }
     *
     * @param Request $request
     * @param int $id Blog post ID
     * @return JsonResponse
     */
    public function getDetailedAnalytics(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $blogPost = BlogPost::findOrFail($id);
        $url = route('public.blog.post', ['slug' => $blogPost->slug]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        // Get total views
        $totalViews = $this->visitStatsService->countUniqueVisits($url, $dateFrom, $dateTo);

        // Get detailed stats
        $filters = [
            'url_pattern' => $url,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $viewsByDay = $this->visitStatsService->getVisitsGroupedByDay($filters);
        $viewsByCountry = $this->visitStatsService->getVisitsGroupedByCountry($filters);

        return response()->json([
            'total_views' => $totalViews,
            'views_by_day' => $viewsByDay,
            'views_by_country' => $viewsByCountry,
        ]);
    }
}
