<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

class RequestLogController extends Controller
{
    public function index(Request $request): Response
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

        $query = LoggedRequest::with([
            'ipAddress',
            'userAgent',
            'mimeType',
            'url',
            'refererUrl',
            'originUrl',
        ])
            ->leftJoin('ip_addresses', 'logged_requests.ip_address_id', '=', 'ip_addresses.id')
            ->leftJoin('user_agents', 'logged_requests.user_agent_id', '=', 'user_agents.id')
            ->leftJoin('urls', 'logged_requests.url_id', '=', 'urls.id')
            ->leftJoin('urls as referer_urls', 'logged_requests.referer_url_id', '=', 'referer_urls.id')
            ->leftJoin('urls as origin_urls', 'logged_requests.origin_url_id', '=', 'origin_urls.id')
            ->leftJoin('mime_types', 'logged_requests.mime_type_id', '=', 'mime_types.id')
            ->leftJoin('ip_address_metadata', 'ip_addresses.id', '=', 'ip_address_metadata.ip_address_id')
            ->leftJoin('user_agent_metadata', 'user_agents.id', '=', 'user_agent_metadata.user_agent_id')
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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_addresses.ip', 'like', "%{$search}%")
                    ->orWhere('urls.url', 'like', "%{$search}%")
                    ->orWhere('user_agents.user_agent', 'like', "%{$search}%")
                    ->orWhere('logged_requests.method', 'like', "%{$search}%")
                    ->orWhere('logged_requests.status_code', 'like', "%{$search}%");
            });
        }

        // Bot status filter (combining old and new detection)
        if ($isBot !== 'all') {
            $query->where(function ($q) use ($isBot) {
                if ($isBot === 'true') {
                    $q->where('user_agent_metadata.is_bot', true)
                        ->orWhere('logged_requests.is_bot_by_frequency', true)
                        ->orWhere('logged_requests.is_bot_by_user_agent', true)
                        ->orWhere('logged_requests.is_bot_by_parameters', true);
                } else {
                    $q->where(function ($subQ) {
                        $subQ->whereNull('user_agent_metadata.is_bot')
                            ->orWhere('user_agent_metadata.is_bot', false);
                    })
                        ->where('logged_requests.is_bot_by_frequency', false)
                        ->where('logged_requests.is_bot_by_user_agent', false)
                        ->where('logged_requests.is_bot_by_parameters', false);
                }
            });
        }

        // Include specific user agents
        if (! empty($includeUserAgents)) {
            $query->where(function ($q) use ($includeUserAgents) {
                foreach ($includeUserAgents as $userAgent) {
                    $q->orWhere('user_agents.user_agent', 'like', "%{$userAgent}%");
                }
            });
        }

        // Exclude specific user agents
        if (! empty($excludeUserAgents)) {
            foreach ($excludeUserAgents as $userAgent) {
                $query->where('user_agents.user_agent', 'not like', "%{$userAgent}%");
            }
        }

        // Include specific IP addresses
        if (! empty($includeIps)) {
            $query->whereIn('ip_addresses.ip', $includeIps);
        }

        // Exclude specific IP addresses
        if (! empty($excludeIps)) {
            $query->whereNotIn('ip_addresses.ip', $excludeIps);
        }

        // Date range filter
        if ($dateFrom) {
            $query->where('logged_requests.created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('logged_requests.created_at', '<=', $dateTo.' 23:59:59');
        }

        // Exclude connected users' IP addresses
        if ($excludeConnectedUsersIps) {
            $query->whereNotIn('logged_requests.ip_address_id', function ($subquery) {
                $subquery->select('lr.ip_address_id')
                    ->from('logged_requests as lr')
                    ->whereNotNull('lr.user_id')
                    ->distinct();
            });
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
}
