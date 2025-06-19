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
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

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
                'user_agent_metadata.is_bot',
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

        $requests = $query->paginate($perPage);

        return Inertia::render('dashboard/requests-log/List', [
            'requests' => $requests,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }
}
