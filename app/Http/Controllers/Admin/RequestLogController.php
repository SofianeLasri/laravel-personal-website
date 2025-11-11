<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\FilteredRequestQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RequestLogController extends Controller
{
    public function index(Request $request, FilteredRequestQueryService $queryService): Response
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
            'is_bot' => 'nullable|in:true,false,all',
            'include_user_agents' => 'nullable|array',
            'include_user_agents.*' => 'string',
            'exclude_user_agents' => 'nullable|array',
            'exclude_user_agents.*' => 'string',
            'include_ips' => 'nullable|array',
            'include_ips.*' => 'string|ip',
            'exclude_ips' => 'nullable|array',
            'exclude_ips.*' => 'string|ip',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'exclude_connected_users_ips' => 'nullable|boolean',
        ]);

        // Extract filter parameters
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $isBot = $request->get('is_bot', 'all');
        $includeUserAgents = $request->get('include_user_agents', []);
        $excludeUserAgents = $request->get('exclude_user_agents', []);
        $includeIps = $request->get('include_ips', []);
        $excludeIps = $request->get('exclude_ips', []);
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $excludeConnectedUsersIps = $request->boolean('exclude_connected_users_ips', false);

        // Build base query with all necessary joins and selects
        $query = $queryService->buildBaseQuery()
            ->with([
                'ipAddress',
                'userAgent',
                'mimeType',
                'url',
                'refererUrl',
                'originUrl',
            ])
            ->select([
                'logged_requests.*',
                'ip_addresses.ip as ip_address',
                'user_agents.user_agent',
                'urls.url as request_url',
                'referer_urls.url as referer_url',
                'origin_urls.url as origin_url',
                'mime_types.mime_type',
                'ip_address_metadata.country_code as geo_country_code',
                'ip_address_metadata.lat as geo_lat',
                'ip_address_metadata.lon as geo_lon',
                'ip_address_metadata.avg_request_interval',
                'ip_address_metadata.total_requests as ip_total_requests',
                'user_agent_metadata.is_bot',
                'logged_requests.is_bot_by_frequency',
                'logged_requests.is_bot_by_user_agent',
                'logged_requests.is_bot_by_parameters',
                'logged_requests.bot_detection_metadata',
            ])
            ->orderBy('logged_requests.created_at', 'desc');

        // Apply filters using the unified service methods
        $queryService->applySearchFilter($query, $search);

        // Bot filter: convert 'true'/'false' string to boolean, 'all' to null (no filtering)
        $botFilter = $isBot === 'all' ? null : ($isBot === 'true');
        $queryService->applyBotFilters($query, $botFilter === null ? null : ! $botFilter);

        $queryService->applyUserAgentFilters($query, $includeUserAgents, $excludeUserAgents);
        $queryService->applyIpFilters($query, $includeIps, $excludeIps);
        $queryService->applyDateRangeFilter($query, $dateFrom, $dateTo);

        // Apply authenticated users filter if requested
        if ($excludeConnectedUsersIps) {
            $queryService->applyAuthenticatedUserFilters($query, true);
        }

        $requests = $query->paginate($perPage);

        return Inertia::render('dashboard/requests-log/List', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'is_bot' => $isBot,
                'include_user_agents' => $includeUserAgents,
                'exclude_user_agents' => $excludeUserAgents,
                'include_ips' => $includeIps,
                'exclude_ips' => $excludeIps,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'exclude_connected_users_ips' => $excludeConnectedUsersIps,
            ],
        ]);
    }

    public function markAsBot(Request $request): JsonResponse
    {
        $request->validate([
            'request_ids' => 'required|array',
            'request_ids.*' => 'integer|exists:logged_requests,id',
        ]);

        $requestIds = $request->input('request_ids');

        // Marquer les requêtes comme bot (manuellement détectées) - utilisation directe de DB
        $updatedCount = DB::table('logged_requests')
            ->whereIn('id', $requestIds)
            ->update([
                'is_bot_by_user_agent' => 1,  // Utiliser 1 au lieu de true pour MySQL/SQLite
                'bot_detection_metadata' => json_encode([
                    'manually_flagged' => true,
                    'flagged_at' => now()->toDateTimeString(),
                    'flagged_by' => auth()->id(),
                    'reason' => 'Manuellement marqué comme bot via le dashboard',
                ]),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => $updatedCount.' requête(s) marquée(s) comme bot avec succès',
            'updated_count' => $updatedCount,
            'requested_ids' => $requestIds,
        ]);
    }
}
