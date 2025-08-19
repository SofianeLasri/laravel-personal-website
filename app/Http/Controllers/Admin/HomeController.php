<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/Dashboard');
    }

    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

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

        $visits = LoggedRequest::select([
            'logged_requests.url_id',
            'logged_requests.referer_url_id',
            'logged_requests.origin_url_id',
            'logged_requests.ip_address_id',
            'ip_address_metadata.country_code',
            'logged_requests.created_at',
            'urls.url',
            'referer_url.url as referer_url',
            'origin_url.url as origin_url'])
            ->distinct(['logged_requests.url_id', 'logged_requests.ip_address_id'])
            ->join('urls', 'logged_requests.url_id', '=', 'urls.id')
            ->leftJoin('urls as referer_url', 'logged_requests.referer_url_id', '=', 'referer_url.id')
            ->leftJoin('urls as origin_url', 'logged_requests.origin_url_id', '=', 'origin_url.id')
            ->join('user_agent_metadata', 'logged_requests.user_agent_id', '=', 'user_agent_metadata.user_agent_id')
            ->join('ip_address_metadata', 'logged_requests.ip_address_id', '=', 'ip_address_metadata.ip_address_id')
            ->join('ip_addresses', 'logged_requests.ip_address_id', '=', 'ip_addresses.id')
            ->whereLike('urls.url', config('app.url').'%')
            ->whereNotIn('urls.url', $individualExcludedRoutes)
            ->where('user_agent_metadata.is_bot', false)
            ->where('logged_requests.is_bot_by_frequency', false)
            ->where('logged_requests.is_bot_by_user_agent', false)
            ->where('logged_requests.is_bot_by_parameters', false)
            ->where('status_code', 200)
            ->whereNull('logged_requests.user_id')
            ->whereNotIn('ip_addresses.id', function ($query) {
                $query->select('ip_addresses.id')
                    ->from('ip_addresses')
                    ->join('logged_requests', 'logged_requests.ip_address_id', '=', 'ip_addresses.id')
                    ->whereNotNull('logged_requests.user_id');
            })
            ->get();

        $now = now();
        $totalVisitsPastTwentyFourHours = $visits->where('created_at', '>=', $now->copy()->subDay())->count();
        $totalVisitsPastSevenDays = $visits->where('created_at', '>=', $now->copy()->subDays(7))->count();
        $totalVisitsPastThirtyDays = $visits->where('created_at', '>=', $now->copy()->subDays(30))->count();
        $totalVisitsAllTime = $visits->count();

        $periods = [
            now()->format('Y-m-d') => 'Aujourd\'hui',
            now()->subDay()->format('Y-m-d') => 'Hier',
            now()->subDays(7)->format('Y-m-d') => 'Les 7 derniers jours',
            now()->subDays(30)->format('Y-m-d') => 'Les 30 derniers jours',
            now()->startOfMonth()->format('Y-m-d') => 'Ce mois-ci',
            now()->subMonth()->startOfMonth()->format('Y-m-d') => 'Le mois dernier',
        ];

        if ($visits->isNotEmpty()) {
            $periods[$visits->min('created_at')->format('Y-m-d')] = 'Depuis le début';
        }

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $dateEnd = $request->input('end_date', now()->format('Y-m-d'));

        $selectedPeriod = $startDate;

        // Now, all the stats are calculated for the selected period
        $visitsPerDay = $visits->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $dateEnd)
            ->groupBy(fn ($visit) => Carbon::parse($visit->created_at)->format('Y-m-d'))
            ->map(fn ($group) => ['date' => $group->first()->created_at->format('Y-m-d'), 'count' => $group->count()])
            ->values();

        $visitsByCountry = $visits->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $dateEnd)
            ->groupBy('country_code')
            ->map(fn ($group, $country) => ['country_code' => $country, 'count' => $group->count()])
            ->values();

        $mostVisitedPages = $visits->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $dateEnd)
            ->groupBy('url')
            ->map(fn ($group, $url) => ['url' => $url, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        $bestsReferrers = $visits->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $dateEnd)
            ->groupBy('referer_url')
            ->map(fn ($group, $url) => ['url' => $url, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        $bestOrigins = $visits->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $dateEnd)
            ->groupBy('origin_url')
            ->map(fn ($group, $url) => ['url' => $url, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        return response()->json([
            'totalVisitsPastTwentyFourHours' => $totalVisitsPastTwentyFourHours,
            'totalVisitsPastSevenDays' => $totalVisitsPastSevenDays,
            'totalVisitsPastThirtyDays' => $totalVisitsPastThirtyDays,
            'totalVisitsAllTime' => $totalVisitsAllTime,
            'visitsPerDay' => $visitsPerDay,
            'visitsByCountry' => $visitsByCountry,
            'mostVisitedPages' => $mostVisitedPages,
            'bestsReferrers' => $bestsReferrers,
            'bestOrigins' => $bestOrigins,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
        ]);
    }
}
