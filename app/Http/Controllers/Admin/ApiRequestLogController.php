<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Services\ApiRequestLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiRequestLogController extends Controller
{
    /**
     * Display a listing of the API request logs.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string',
            'provider' => 'nullable|string|in:openai,anthropic,all',
            'status' => 'nullable|string|in:success,error,timeout,fallback,all',
            'cached' => 'nullable|string|in:true,false,all',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $provider = $request->get('provider', 'all');
        $status = $request->get('status', 'all');
        $cached = $request->get('cached', 'all');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = ApiRequestLog::query()
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('system_prompt', 'like', "%{$search}%")
                    ->orWhere('user_prompt', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        // Provider filter
        if ($provider !== 'all') {
            $query->where('provider', $provider);
        }

        // Status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Cached filter
        if ($cached !== 'all') {
            $query->where('cached', $cached === 'true');
        }

        // Date range filter
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        $logs = $query->paginate($perPage);

        // Transform logs for display
        $logs->through(function ($log) {
            return [
                'id' => $log->id,
                'provider' => $log->provider,
                'model' => $log->model,
                'status' => $log->status,
                'status_color' => $log->getStatusColor(),
                'http_status_code' => $log->http_status_code,
                'system_prompt_truncated' => $log->getTruncatedSystemPrompt(50),
                'user_prompt_truncated' => $log->getTruncatedUserPrompt(50),
                'system_prompt' => $log->system_prompt,
                'user_prompt' => $log->user_prompt,
                'error_message' => $log->error_message,
                'prompt_tokens' => $log->prompt_tokens,
                'completion_tokens' => $log->completion_tokens,
                'total_tokens' => $log->total_tokens,
                'response_time' => $log->response_time,
                'estimated_cost' => $log->estimated_cost,
                'cached' => $log->cached,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_at_iso' => $log->created_at->toISOString(),
            ];
        });

        // Get statistics
        $logger = app(ApiRequestLogger::class);
        $statistics = $logger->getStatistics(30);

        return Inertia::render('dashboard/api-logs/List', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'provider' => $provider,
                'status' => $status,
                'cached' => $cached,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'statistics' => $statistics,
        ]);
    }

    /**
     * Display the specified API request log.
     */
    public function show(ApiRequestLog $apiRequestLog): Response
    {
        return Inertia::render('dashboard/api-logs/Show', [
            'log' => [
                'id' => $apiRequestLog->id,
                'provider' => $apiRequestLog->provider,
                'model' => $apiRequestLog->model,
                'endpoint' => $apiRequestLog->endpoint,
                'status' => $apiRequestLog->status,
                'status_color' => $apiRequestLog->getStatusColor(),
                'http_status_code' => $apiRequestLog->http_status_code,
                'error_message' => $apiRequestLog->error_message,
                'system_prompt' => $apiRequestLog->system_prompt,
                'user_prompt' => $apiRequestLog->user_prompt,
                'response' => $apiRequestLog->response,
                'prompt_tokens' => $apiRequestLog->prompt_tokens,
                'completion_tokens' => $apiRequestLog->completion_tokens,
                'total_tokens' => $apiRequestLog->total_tokens,
                'response_time' => $apiRequestLog->response_time,
                'estimated_cost' => $apiRequestLog->estimated_cost,
                'fallback_provider' => $apiRequestLog->fallback_provider,
                'metadata' => $apiRequestLog->metadata,
                'cached' => $apiRequestLog->cached,
                'created_at' => $apiRequestLog->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
