<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\VisitStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/Dashboard');
    }

    public function stats(Request $request, VisitStatsService $visitStatsService): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        // Build list of excluded routes (dashboard, login, etc.)
        $excludedRoutes = [
            'dashboard',
            'login',
            'register',
            'password',
            'email',
        ];

        $routes = Route::getRoutes()->getRoutes();
        $individualExcludedRoutes = [];
        foreach ($routes as $route) {
            if (Str::startsWith($route->uri, $excludedRoutes)) {
                $individualExcludedRoutes[] = config('app.url').'/'.$route->uri;
            }
        }

        // Prepare filters for analytics queries
        $filters = [
            'url_pattern' => config('app.url').'%',
            'excluded_urls' => $individualExcludedRoutes,
        ];

        // Get all visits for period calculations
        $visits = $visitStatsService->getUniqueVisits($filters);

        // Calculate totals by period (reusing $visits to avoid duplicate query)
        $totalsByPeriods = $visitStatsService->getTotalVisitsByPeriods($filters, $visits);

        // Get available periods for selector
        $periods = $visitStatsService->getAvailablePeriods($visits);

        // Get selected date range
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $dateEnd = $request->input('end_date', now()->format('Y-m-d'));

        // Apply date filters for detailed stats
        $filters['date_from'] = $startDate;
        $filters['date_to'] = $dateEnd;

        // Fetch visits once with date filters to avoid N+1 queries
        $visitsForPeriod = $visitStatsService->getUniqueVisits($filters);

        // Get aggregated statistics for the selected period (reusing $visitsForPeriod)
        $visitsPerDay = $visitStatsService->getVisitsGroupedByDay($filters, $visitsForPeriod);
        $visitsByCountry = $visitStatsService->getVisitsGroupedByCountry($filters, $visitsForPeriod);
        $mostVisitedPages = $visitStatsService->getMostVisitedPages($filters, 10, $visitsForPeriod);
        $bestsReferrers = $visitStatsService->getBestReferrers($filters, 10, $visitsForPeriod);
        $bestOrigins = $visitStatsService->getBestOrigins($filters, 10, $visitsForPeriod);

        return response()->json([
            'totalVisitsPastTwentyFourHours' => $totalsByPeriods['past_24h'],
            'totalVisitsPastSevenDays' => $totalsByPeriods['past_7d'],
            'totalVisitsPastThirtyDays' => $totalsByPeriods['past_30d'],
            'totalVisitsAllTime' => $totalsByPeriods['all_time'],
            'visitsPerDay' => $visitsPerDay,
            'visitsByCountry' => $visitsByCountry,
            'mostVisitedPages' => $mostVisitedPages,
            'bestsReferrers' => $bestsReferrers,
            'bestOrigins' => $bestOrigins,
            'periods' => $periods,
            'selectedPeriod' => $startDate,
        ]);
    }
}
