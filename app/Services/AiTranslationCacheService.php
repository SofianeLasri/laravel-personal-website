<?php

namespace App\Services;

use App\Models\AiTranslationCache;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiTranslationCacheService
{
    /**
     * Generate a cache key from provider, system prompt and user prompt
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $systemPrompt  The system prompt
     * @param  string  $userPrompt  The user prompt
     * @return string The cache key
     */
    public function generateCacheKey(string $provider, string $systemPrompt, string $userPrompt): string
    {
        $content = $provider.'|'.$systemPrompt.'|'.$userPrompt;

        return hash('sha256', $content);
    }

    /**
     * Get cached response if available and not expired
     *
     * @param  string  $cacheKey  The cache key
     * @param  int  $ttlInSeconds  The TTL in seconds
     * @return array<string, mixed>|null The cached response or null
     */
    public function get(string $cacheKey, int $ttlInSeconds): ?array
    {
        try {
            /** @var AiTranslationCache|null $cache */
            $cache = AiTranslationCache::where('cache_key', $cacheKey)->first();

            if (! $cache) {
                return null;
            }

            if ($cache->isExpired($ttlInSeconds)) {
                Log::info('AI translation cache expired', [
                    'cache_key' => $cacheKey,
                    'created_at' => $cache->created_at,
                    'ttl' => $ttlInSeconds,
                ]);

                return null;
            }

            $cache->incrementHits();

            Log::info('AI translation cache hit', [
                'cache_key' => $cacheKey,
                'hits' => $cache->hits,
            ]);

            return $cache->response;
        } catch (Exception $e) {
            Log::error('Error retrieving AI translation cache', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Store a response in cache
     *
     * @param  string  $provider  The AI provider name
     * @param  string  $systemPrompt  The system prompt
     * @param  string  $userPrompt  The user prompt
     * @param  array<string, mixed>  $response  The response to cache
     * @return bool Success status
     */
    public function put(string $provider, string $systemPrompt, string $userPrompt, array $response): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($provider, $systemPrompt, $userPrompt);

            AiTranslationCache::updateOrCreate(
                ['cache_key' => $cacheKey],
                [
                    'provider' => $provider,
                    'system_prompt' => $systemPrompt,
                    'user_prompt' => $userPrompt,
                    'response' => $response,
                    'hits' => 0,
                ]
            );

            Log::info('AI translation cached', [
                'cache_key' => $cacheKey,
                'provider' => $provider,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Error storing AI translation cache', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear expired cache entries
     *
     * @param  int  $ttlInSeconds  The TTL in seconds
     * @return int Number of deleted entries
     */
    public function clearExpired(int $ttlInSeconds): int
    {
        try {
            $expiryDate = now()->subSeconds($ttlInSeconds);

            $count = AiTranslationCache::where('created_at', '<', $expiryDate)->count();

            AiTranslationCache::where('created_at', '<', $expiryDate)->delete();

            Log::info('Cleared expired AI translation cache entries', [
                'count' => $count,
                'ttl' => $ttlInSeconds,
            ]);

            return $count;
        } catch (Exception $e) {
            Log::error('Error clearing expired AI translation cache', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Clear all cache entries
     *
     * @return int Number of deleted entries
     */
    public function clearAll(): int
    {
        try {
            $count = AiTranslationCache::count();
            AiTranslationCache::truncate();

            Log::info('Cleared all AI translation cache entries', [
                'count' => $count,
            ]);

            return $count;
        } catch (Exception $e) {
            Log::error('Error clearing all AI translation cache', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get cache statistics
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getStatistics(): array
    {
        try {
            return [
                'total_entries' => AiTranslationCache::count(),
                'total_hits' => AiTranslationCache::sum('hits'),
                'average_hits' => AiTranslationCache::avg('hits') ?? 0,
                'most_used' => AiTranslationCache::orderBy('hits', 'desc')
                    ->limit(5)
                    ->get(['cache_key', 'provider', 'hits', 'created_at'])
                    ->toArray(),
                'providers' => AiTranslationCache::select('provider', DB::raw('count(*) as count'), DB::raw('sum(hits) as total_hits'))
                    ->groupBy('provider')
                    ->get()
                    ->toArray(),
                'oldest_entry' => AiTranslationCache::min('created_at'),
                'newest_entry' => AiTranslationCache::max('created_at'),
            ];
        } catch (Exception $e) {
            Log::error('Error getting AI translation cache statistics', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if caching is enabled
     */
    public function isEnabled(): bool
    {
        return config('ai-provider.cache.enabled', true);
    }

    /**
     * Get the cache TTL from configuration
     *
     * @return int TTL in seconds
     */
    public function getTtl(): int
    {
        $ttl = config('ai-provider.cache.ttl');

        return $ttl !== null ? (int) $ttl : 2592000; // 30 days default
    }

    /**
     * Remove a specific cache entry
     *
     * @param  string  $cacheKey  The cache key
     * @return bool Success status
     */
    public function forget(string $cacheKey): bool
    {
        try {
            $deleted = AiTranslationCache::where('cache_key', $cacheKey)->delete();

            if ($deleted > 0) {
                Log::info('AI translation cache entry deleted', [
                    'cache_key' => $cacheKey,
                ]);
            }

            return $deleted > 0;
        } catch (Exception $e) {
            Log::error('Error deleting AI translation cache entry', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
