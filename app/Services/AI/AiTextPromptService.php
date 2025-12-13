<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AiTranslationCacheService;
use Illuminate\Support\Facades\Log;

/**
 * Service for text-only AI prompts with caching support.
 * Orchestrates cache checks, API calls to providers, and cache storage.
 */
class AiTextPromptService
{
    public function __construct(
        private readonly AiApiClientService $apiClient,
        private readonly AiTranslationCacheService $cacheService
    ) {}

    /**
     * Send a text prompt to the configured AI provider with caching support.
     *
     * @param  string  $systemRole  The system role/instructions
     * @param  string  $prompt  The user prompt
     * @return array<string, mixed> The parsed JSON response from the AI provider
     */
    public function prompt(string $systemRole, string $prompt): array
    {
        $selectedProvider = config('ai-provider.selected-provider');
        $providerConfig = config('ai-provider.providers.'.$selectedProvider);

        // Check cache if enabled
        if ($this->cacheService->isEnabled()) {
            $cacheKey = $this->cacheService->generateCacheKey($selectedProvider, $systemRole, $prompt);
            $cached = $this->cacheService->get($cacheKey, $this->cacheService->getTtl());

            if ($cached !== null) {
                Log::info('Using cached AI response', [
                    'provider' => $selectedProvider,
                    'cache_key' => $cacheKey,
                ]);

                return $cached;
            }
        }

        // Call the API using the appropriate provider
        $response = $selectedProvider === 'anthropic'
            ? $this->apiClient->callAnthropic($providerConfig, $systemRole, $prompt)
            : $this->apiClient->callOpenAi($providerConfig, $systemRole, $prompt);

        // Store in cache if enabled
        if ($this->cacheService->isEnabled()) {
            $this->cacheService->put($selectedProvider, $systemRole, $prompt, $response);
        }

        return $response;
    }
}
