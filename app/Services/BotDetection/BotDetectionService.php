<?php

namespace App\Services\BotDetection;

use App\Models\IpAddressMetadata;
use foroco\BrowserDetection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SlProjects\LaravelRequestLogger\app\Models\LoggedRequest;

class BotDetectionService
{
    private BrowserDetection $browserDetection;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $suspiciousDevicePatterns = [
        'android' => [
            'versions' => ['4.4', '4.3', '4.2', '4.1', '4.0'],
            'devices' => ['Galaxy Note 4', 'Galaxy S4', 'Galaxy S3'],
            'max_requests_per_minute' => 10,
        ],
    ];

    private float $defaultAvgRequestInterval = 5.0;

    private float $suspiciousFrequencyMultiplier = 0.3;

    private int $minRequestsForAnalysis = 5;

    public function __construct()
    {
        $this->browserDetection = new BrowserDetection;
    }

    /**
     * Analyze a specific request for bot behavior
     *
     * @return array{is_bot: bool, reasons: array<string>}
     */
    public function analyzeRequest(LoggedRequest $request): array
    {
        // Check if this request was manually flagged
        $existingMetadata = DB::table('logged_requests')
            ->where('id', $request->id)
            ->value('bot_detection_metadata');

        if ($existingMetadata) {
            $metadata = json_decode($existingMetadata, true);
            if (isset($metadata['manually_flagged']) && $metadata['manually_flagged'] === true) {
                // Skip re-analysis for manually flagged requests
                return [
                    'is_bot' => true,
                    'reasons' => [$metadata['reason'] ?? 'Manuellement marqué comme bot'],
                    'skipped' => true,
                    'skip_reason' => 'Manually flagged - skipping automatic analysis',
                ];
            }
        }

        // Skip analysis for authenticated users
        if ($request->user_id !== null) {
            // Mark as analyzed but not as bot
            DB::table('logged_requests')
                ->where('id', $request->id)
                ->update([
                    'is_bot_by_frequency' => false,
                    'is_bot_by_user_agent' => false,
                    'is_bot_by_parameters' => false,
                    'bot_detection_metadata' => json_encode([
                        'skipped' => true,
                        'reason' => 'Authenticated user',
                    ]),
                    'bot_analyzed_at' => now(),
                ]);

            return [
                'is_bot' => false,
                'reasons' => [],
                'skipped' => true,
                'skip_reason' => 'Authenticated user',
            ];
        }

        $reasons = [];
        $isBotByFrequency = false;
        $isBotByUserAgent = false;
        $isBotByParameters = false;

        // Load relationships
        $request->load(['ipAddress', 'userAgent', 'url', 'refererUrl']);

        // Analyze frequency patterns
        $frequencyAnalysis = $this->analyzeRequestFrequency($request);
        if ($frequencyAnalysis['is_suspicious']) {
            $isBotByFrequency = true;
            $reasons[] = $frequencyAnalysis['reason'] ?? 'Suspicious frequency pattern';
        }

        // Analyze user agent patterns
        if ($request->userAgent) {
            $userAgentAnalysis = $this->analyzeUserAgent(
                $request->userAgent->user_agent,
                $frequencyAnalysis['requests_per_minute'] ?? 0
            );
            if ($userAgentAnalysis['is_suspicious']) {
                $isBotByUserAgent = true;
                $reasons[] = $userAgentAnalysis['reason'] ?? 'Suspicious user agent';
            }
        }

        // Analyze referer patterns
        if ($request->refererUrl) {
            $refererAnalysis = $this->analyzeReferer($request->refererUrl->url);
            if ($refererAnalysis['is_suspicious']) {
                $isBotByUserAgent = true;
                $reasons[] = $refererAnalysis['reason'] ?? 'Suspicious referer';
            }
        }

        // Analyze URL parameters
        if ($request->url) {
            $parameterAnalysis = $this->analyzeUrlParameters($request->url->url);
            if ($parameterAnalysis['is_suspicious']) {
                $isBotByParameters = true;
                $reasons[] = $parameterAnalysis['reason'] ?? 'Suspicious URL parameters';
            }
        }

        // Update the request with analysis results using DB query since model has protected fillable
        $metadata = [
            'reasons' => $reasons,
            'frequency_analysis' => $frequencyAnalysis,
            'user_agent_analysis' => $userAgentAnalysis ?? null,
            'referer_analysis' => $refererAnalysis ?? null,
            'parameter_analysis' => $parameterAnalysis ?? null,
        ];

        DB::table('logged_requests')
            ->where('id', $request->id)
            ->update([
                'is_bot_by_frequency' => $isBotByFrequency,
                'is_bot_by_user_agent' => $isBotByUserAgent,
                'is_bot_by_parameters' => $isBotByParameters,
                'bot_detection_metadata' => json_encode($metadata),
                'bot_analyzed_at' => now(),
            ]);

        return [
            'is_bot' => $isBotByFrequency || $isBotByUserAgent || $isBotByParameters,
            'reasons' => $reasons,
        ];
    }

    /**
     * Analyze request frequency patterns for an IP
     *
     * @return array{is_suspicious: bool, reason?: string, requests_per_minute?: float}
     */
    private function analyzeRequestFrequency(LoggedRequest $request): array
    {
        if (! $request->ipAddress) {
            return ['is_suspicious' => false];
        }

        // Get or create IP metadata
        $ipMetadata = IpAddressMetadata::firstOrCreate(
            ['ip_address_id' => $request->ip_address_id],
            [
                'country_code' => 'XX',
                'first_seen_at' => $request->created_at,
                'last_seen_at' => $request->created_at,
                'total_requests' => 1,
                'avg_request_interval' => null,
            ]
        );

        // Calculate request frequency for this IP in the last hour relative to the request time
        $createdAt = $request->created_at;
        if (! $createdAt) {
            return ['is_suspicious' => false];
        }

        $recentRequests = LoggedRequest::where('ip_address_id', $request->ip_address_id)
            ->where('created_at', '>=', $createdAt->copy()->subHour())
            ->where('created_at', '<=', $createdAt)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($recentRequests->count() < $this->minRequestsForAnalysis) {
            return [
                'is_suspicious' => false,
                'requests_count' => $recentRequests->count(),
                'min_required' => $this->minRequestsForAnalysis,
                'requests_per_minute' => 0,
                'debug' => 'Not enough requests for analysis',
            ];
        }

        // Calculate average interval between requests
        $intervals = [];
        for ($i = 1; $i < $recentRequests->count(); $i++) {
            $currentRequest = $recentRequests->get($i);
            $previousRequest = $recentRequests->get($i - 1);

            if (! $currentRequest || ! $previousRequest) {
                continue;
            }

            $currentCreatedAt = $currentRequest->created_at;
            $previousCreatedAt = $previousRequest->created_at;

            if (! $currentCreatedAt || ! $previousCreatedAt) {
                continue;
            }

            // Calculate interval in seconds (always positive)
            $interval = abs($currentCreatedAt->diffInSeconds($previousCreatedAt));
            if ($interval > 0) {
                $intervals[] = $interval;
            }
        }

        if (empty($intervals)) {
            return [
                'is_suspicious' => false,
                'debug' => 'No intervals to analyze',
                'requests_count' => $recentRequests->count(),
            ];
        }

        $avgInterval = array_sum($intervals) / count($intervals);
        $requestsPerMinute = $avgInterval > 0 ? 60 / $avgInterval : 0;

        // Update IP metadata
        $ipMetadata->update([
            'last_seen_at' => $request->created_at,
            'total_requests' => $ipMetadata->total_requests + 1,
            'avg_request_interval' => $avgInterval,
        ]);

        // Determine if frequency is suspicious
        // For new IPs or IPs without history, be more lenient on the first pass
        $expectedInterval = $ipMetadata->avg_request_interval ?? $this->defaultAvgRequestInterval;
        $suspiciousThreshold = $expectedInterval * $this->suspiciousFrequencyMultiplier;

        // High frequency detection: more than 30 requests per minute with interval less than 2 seconds
        // OR average interval less than 1.5 seconds (40+ requests per minute)
        // OR interval less than suspicious threshold with high request rate
        if (($requestsPerMinute > 30 && $avgInterval < 2) ||
            ($avgInterval <= 1.5) ||
            ($avgInterval < $suspiciousThreshold && $requestsPerMinute > 20)) {
            return [
                'is_suspicious' => true,
                'reason' => sprintf(
                    'High request frequency: %.1f requests/minute (avg interval: %.2fs)',
                    $requestsPerMinute,
                    $avgInterval
                ),
                'requests_per_minute' => $requestsPerMinute,
            ];
        }

        return [
            'is_suspicious' => false,
            'requests_per_minute' => $requestsPerMinute,
            'requests_count' => $recentRequests->count(),
            'avg_interval' => $avgInterval,
        ];
    }

    /**
     * Analyze user agent for suspicious patterns
     *
     * @return array{is_suspicious: bool, reason?: string}
     */
    private function analyzeUserAgent(string $userAgentString, float $requestsPerMinute): array
    {
        $result = $this->browserDetection->getAll($userAgentString);

        if (! $result) {
            return ['is_suspicious' => false];
        }

        // Check if it's already identified as a bot
        if (isset($result['bot_name']) && $result['bot_name']) {
            return [
                'is_suspicious' => true,
                'reason' => sprintf('Known bot detected: %s', $result['bot_name']),
            ];
        }

        // Check for suspicious Android patterns
        if (isset($result['os_name']) && strtolower($result['os_name']) === 'android') {
            $osVersion = $result['os_version'] ?? '';
            $deviceName = $result['device_name'] ?? '';

            foreach ($this->suspiciousDevicePatterns['android']['versions'] as $suspiciousVersion) {
                if (str_starts_with($osVersion, $suspiciousVersion)) {
                    foreach ($this->suspiciousDevicePatterns['android']['devices'] as $suspiciousDevice) {
                        if (stripos($deviceName, $suspiciousDevice) !== false) {
                            if ($requestsPerMinute > $this->suspiciousDevicePatterns['android']['max_requests_per_minute']) {
                                return [
                                    'is_suspicious' => true,
                                    'reason' => sprintf(
                                        'Suspicious pattern: Old Android %s device (%s) with high request rate (%.1f req/min)',
                                        $osVersion,
                                        $deviceName,
                                        $requestsPerMinute
                                    ),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Check for missing or suspicious browser information
        if (! isset($result['browser_name']) || empty($result['browser_name'])) {
            if ($requestsPerMinute > 20) {
                return [
                    'is_suspicious' => true,
                    'reason' => 'No browser identified with high request rate',
                ];
            }
        }

        return ['is_suspicious' => false];
    }

    /**
     * Analyze referer for suspicious patterns
     *
     * @return array{is_suspicious: bool, reason?: string}
     */
    private function analyzeReferer(string $referer): array
    {
        $suspiciousReferers = config('bot-detection.suspicious_referers', []);

        if (empty($suspiciousReferers)) {
            return ['is_suspicious' => false];
        }

        $refererLower = strtolower($referer);

        foreach ($suspiciousReferers as $suspiciousTerm) {
            if (str_contains($refererLower, strtolower($suspiciousTerm))) {
                return [
                    'is_suspicious' => true,
                    'reason' => sprintf('Suspicious referer detected: contains "%s"', $suspiciousTerm),
                    'matched_term' => $suspiciousTerm,
                    'referer' => $referer,
                ];
            }
        }

        return ['is_suspicious' => false];
    }

    /**
     * Analyze URL parameters for suspicious patterns
     *
     * @return array{is_suspicious: bool, reason?: string}
     */
    private function analyzeUrlParameters(string $url): array
    {
        $parsedUrl = parse_url($url);
        $queryString = $parsedUrl['query'] ?? '';

        if (empty($queryString)) {
            return ['is_suspicious' => false];
        }

        parse_str($queryString, $params);

        // Get whitelisted parameters for this route
        $path = $parsedUrl['path'] ?? '';
        $whitelistedParams = $this->getWhitelistedParameters($path);

        // Check for unexpected parameters
        $unexpectedParams = array_diff(array_keys($params), $whitelistedParams);

        if (! empty($unexpectedParams)) {
            // Check if parameters look like random/bot-generated
            foreach ($unexpectedParams as $param) {
                if ($this->isRandomParameter((string) $param, $params[$param] ?? '')) {
                    return [
                        'is_suspicious' => true,
                        'reason' => sprintf(
                            'Suspicious URL parameters detected: %s',
                            implode(', ', $unexpectedParams)
                        ),
                    ];
                }
            }
        }

        return ['is_suspicious' => false];
    }

    /**
     * Check if a parameter looks randomly generated
     */
    private function isRandomParameter(string $key, mixed $value): bool
    {
        // Check for common bot patterns
        $suspiciousPatterns = [
            '/^[a-z0-9]{32,}$/i', // Long random strings
            '/^[0-9]{10,}$/', // Long numeric strings
            '/^(test|debug|admin|hack)/i', // Common attack patterns
        ];

        $valueStr = is_string($value) ? $value : json_encode($value);
        if ($valueStr === false) {
            $valueStr = '';
        }

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $key) || preg_match($pattern, $valueStr)) {
                return true;
            }
        }

        // Check for high entropy (randomness)
        if (strlen($valueStr) > 10) {
            $entropy = $this->calculateEntropy($valueStr);
            if ($entropy > 4.5) { // High entropy suggests randomness
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate Shannon entropy of a string
     */
    private function calculateEntropy(string $string): float
    {
        $frequencies = array_count_values(str_split($string));
        $length = strlen($string);
        $entropy = 0;

        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    /**
     * Get whitelisted parameters for a given route
     *
     * @return array<string>
     */
    private function getWhitelistedParameters(string $path): array
    {
        // This will be populated by RouteParameterWhitelistService
        return RouteParameterWhitelistService::getWhitelistedParameters($path);
    }

    /**
     * Batch analyze unanalyzed requests (excluding authenticated users)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function analyzeUnanalyzedRequests(int $limit = 100): Collection
    {
        $requests = LoggedRequest::whereNull('bot_analyzed_at')
            ->whereNull('user_id') // Exclude authenticated users
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $results = collect();

        foreach ($requests as $request) {
            $results->push([
                'request_id' => $request->id,
                'analysis' => $this->analyzeRequest($request),
            ]);
        }

        return $results;
    }

    /**
     * Re-analyze requests for IPs that haven't been analyzed recently (excluding authenticated users)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reanalyzeOldRequests(int $hoursAgo = 24, int $limit = 100): Collection
    {
        $cutoffTime = now()->subHours($hoursAgo);

        $ipIds = IpAddressMetadata::where(function ($query) use ($cutoffTime) {
            $query->whereNull('last_bot_analysis_at')
                ->orWhere('last_bot_analysis_at', '<', $cutoffTime);
        })
            ->pluck('ip_address_id');

        $requests = LoggedRequest::whereIn('ip_address_id', $ipIds)
            ->whereNull('user_id') // Exclude authenticated users
            ->where('created_at', '>=', $cutoffTime)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $results = collect();

        foreach ($requests as $request) {
            $results->push([
                'request_id' => $request->id,
                'analysis' => $this->analyzeRequest($request),
            ]);
        }

        // Update last analysis time for these IPs
        IpAddressMetadata::whereIn('ip_address_id', $ipIds)
            ->update(['last_bot_analysis_at' => now()]);

        return $results;
    }
}
