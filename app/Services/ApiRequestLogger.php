<?php

namespace App\Services;

use App\Models\ApiRequestLog;
use Exception;
use Illuminate\Support\Facades\Log;

class ApiRequestLogger
{
    /**
     * Log a successful API request
     *
     * @param string $provider
     * @param string $model
     * @param string $endpoint
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param array<string, mixed> $response
     * @param float $responseTime
     * @param int|null $httpStatusCode
     * @param bool $cached
     * @param array<string, mixed>|null $metadata
     * @return ApiRequestLog|null
     */
    public function logSuccess(
        string $provider,
        string $model,
        string $endpoint,
        string $systemPrompt,
        string $userPrompt,
        array  $response,
        float  $responseTime,
        ?int   $httpStatusCode = 200,
        bool   $cached = false,
        ?array $metadata = null
    ): ?ApiRequestLog
    {
        try {
            $tokens = $this->extractTokenCounts($response, $provider);
            $cost = $this->estimateCost($provider, $model, $tokens['prompt_tokens'], $tokens['completion_tokens']);

            return ApiRequestLog::create([
                'provider' => $provider,
                'model' => $model,
                'endpoint' => $endpoint,
                'status' => 'success',
                'http_status_code' => $httpStatusCode,
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'response' => $response,
                'prompt_tokens' => $tokens['prompt_tokens'],
                'completion_tokens' => $tokens['completion_tokens'],
                'total_tokens' => $tokens['total_tokens'],
                'response_time' => $responseTime,
                'estimated_cost' => $cost,
                'cached' => $cached,
                'metadata' => $metadata,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log API request', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Log an error API request
     *
     * @param string $provider
     * @param string $model
     * @param string $endpoint
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param string $errorMessage
     * @param float $responseTime
     * @param int|null $httpStatusCode
     * @param array<string, mixed>|null $metadata
     * @return ApiRequestLog|null
     */
    public function logError(
        string $provider,
        string $model,
        string $endpoint,
        string $systemPrompt,
        string $userPrompt,
        string $errorMessage,
        float  $responseTime,
        ?int   $httpStatusCode = null,
        ?array $metadata = null
    ): ?ApiRequestLog
    {
        try {
            return ApiRequestLog::create([
                'provider' => $provider,
                'model' => $model,
                'endpoint' => $endpoint,
                'status' => 'error',
                'http_status_code' => $httpStatusCode,
                'error_message' => $errorMessage,
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'response_time' => $responseTime,
                'metadata' => $metadata,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log API error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Log a timeout API request
     *
     * @param string $provider
     * @param string $model
     * @param string $endpoint
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param float $responseTime
     * @param array<string, mixed>|null $metadata
     * @return ApiRequestLog|null
     */
    public function logTimeout(
        string $provider,
        string $model,
        string $endpoint,
        string $systemPrompt,
        string $userPrompt,
        float  $responseTime,
        ?array $metadata = null
    ): ?ApiRequestLog
    {
        try {
            return ApiRequestLog::create([
                'provider' => $provider,
                'model' => $model,
                'endpoint' => $endpoint,
                'status' => 'timeout',
                'error_message' => 'Request timeout',
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'response_time' => $responseTime,
                'metadata' => $metadata,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log API timeout', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract token counts from response based on provider
     *
     * @param array<string, mixed> $response
     * @param string $provider
     * @return array{prompt_tokens: int|null, completion_tokens: int|null, total_tokens: int|null}
     */
    private function extractTokenCounts(array $response, string $provider): array
    {
        $promptTokens = null;
        $completionTokens = null;
        $totalTokens = null;

        if ($provider === 'openai') {
            // OpenAI returns usage information
            $promptTokens = $response['usage']['prompt_tokens'] ?? null;
            $completionTokens = $response['usage']['completion_tokens'] ?? null;
            $totalTokens = $response['usage']['total_tokens'] ?? null;
        } elseif ($provider === 'anthropic') {
            // Anthropic returns usage in a different format
            $promptTokens = $response['usage']['input_tokens'] ?? null;
            $completionTokens = $response['usage']['output_tokens'] ?? null;
            $totalTokens = ($promptTokens && $completionTokens)
                ? $promptTokens + $completionTokens
                : null;
        }

        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];
    }

    /**
     * Estimate cost based on provider, model and token counts
     *
     * @param string $provider
     * @param string $model
     * @param int|null $promptTokens
     * @param int|null $completionTokens
     * @return float|null
     */
    private function estimateCost(string $provider, string $model, ?int $promptTokens, ?int $completionTokens): ?float
    {
        if (!$promptTokens || !$completionTokens) {
            return null;
        }

        // Get pricing from config or use defaults
        $pricing = $this->getPricing($provider, $model);

        if (!$pricing) {
            return null;
        }

        $promptCost = ($promptTokens / 1000) * $pricing['input'];
        $completionCost = ($completionTokens / 1000) * $pricing['output'];

        return round($promptCost + $completionCost, 6);
    }

    /**
     * Get pricing for provider and model
     *
     * @param string $provider
     * @param string $model
     * @return array{input: float, output: float}|null
     */
    private function getPricing(string $provider, string $model): ?array
    {
        // Default pricing per 1k tokens in USD
        $pricing = [
            'openai' => [
                'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
                'gpt-4o' => ['input' => 0.005, 'output' => 0.015],
                'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
                'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            ],
            'anthropic' => [
                'claude-3-5-sonnet-20241022' => ['input' => 0.003, 'output' => 0.015],
                'claude-3-opus-20240229' => ['input' => 0.015, 'output' => 0.075],
                'claude-3-sonnet-20240229' => ['input' => 0.003, 'output' => 0.015],
                'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125],
            ],
        ];

        return $pricing[$provider][$model] ?? null;
    }

    /**
     * Get statistics for dashboard
     *
     * @param int $days Number of days to look back
     * @return array<string, mixed>
     */
    public function getStatistics(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        try {
            $logs = ApiRequestLog::where('created_at', '>=', $startDate)->get();

            return [
                'total_requests' => $logs->count(),
                'successful_requests' => $logs->where('status', 'success')->count(),
                'error_requests' => $logs->where('status', 'error')->count(),
                'timeout_requests' => $logs->where('status', 'timeout')->count(),
                'cached_requests' => $logs->where('cached', true)->count(),
                'total_cost' => $logs->sum('estimated_cost'),
                'average_response_time' => $logs->avg('response_time'),
                'total_tokens' => $logs->sum('total_tokens'),
                'by_provider' => $logs->groupBy('provider')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'cost' => $group->sum('estimated_cost'),
                        'tokens' => $group->sum('total_tokens'),
                        'avg_response_time' => $group->avg('response_time'),
                    ];
                })->toArray(),
                'by_status' => $logs->groupBy('status')->map->count()->toArray(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get API statistics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}