<?php

namespace Tests\Unit;

use App\Models\AiTranslationCache;
use App\Services\AiProviderService;
use App\Services\AiTranslationCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderServiceCacheTest extends TestCase
{
    use RefreshDatabase;

    private AiProviderService $service;
    private AiTranslationCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiProviderService();
        $this->cacheService = new AiTranslationCacheService();
    }

    public function test_prompt_uses_cache_when_enabled(): void
    {
        Config::set('ai-provider.cache.enabled', true);
        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai.api-key', 'test-key');

        $systemRole = 'You are a translator';
        $prompt = 'Translate hello to French';
        $response = ['translation' => 'Bonjour'];

        // Mock the HTTP response for the first call
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($response)
                        ]
                    ]
                ]
            ], 200),
        ]);

        // First call - should hit the API
        $result1 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response, $result1);

        // Verify cache was created
        $cacheKey = $this->cacheService->generateCacheKey('openai', $systemRole, $prompt);
        $cacheEntry = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertNotNull($cacheEntry);
        $this->assertEquals(0, $cacheEntry->hits);

        // Second call - should use cache
        Http::fake([
            'api.openai.com/*' => Http::response('Should not be called', 500),
        ]);

        $result2 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response, $result2);

        // Verify cache hits were incremented
        $cacheEntry->refresh();
        $this->assertEquals(1, $cacheEntry->hits);
    }

    public function test_prompt_bypasses_cache_when_disabled(): void
    {
        Config::set('ai-provider.cache.enabled', false);
        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai.api-key', 'test-key');

        $systemRole = 'You are a translator';
        $prompt = 'Translate hello to French';
        $response1 = ['translation' => 'Bonjour'];
        $response2 = ['translation' => 'Salut'];

        // Mock different responses for each call
        $callCount = 0;
        Http::fake(function ($request) use (&$callCount, $response1, $response2) {
            $callCount++;
            $response = $callCount === 1 ? $response1 : $response2;
            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($response)
                        ]
                    ]
                ]
            ], 200);
        });

        // First call
        $result1 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response1, $result1);

        // Second call - should hit API again since cache is disabled
        $result2 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response2, $result2);

        // Verify no cache was created
        $cacheKey = $this->cacheService->generateCacheKey('openai', $systemRole, $prompt);
        $cacheEntry = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertNull($cacheEntry);
    }

    public function test_prompt_with_anthropic_uses_cache(): void
    {
        Config::set('ai-provider.cache.enabled', true);
        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic.api-key', 'test-key');

        $systemRole = 'You are a helpful assistant';
        $prompt = 'What is 2+2?';
        $response = ['answer' => '4'];

        // Mock the HTTP response for the first call
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode($response)
                    ]
                ]
            ], 200),
        ]);

        // First call - should hit the API
        $result1 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response, $result1);

        // Verify cache was created with correct provider
        $cacheKey = $this->cacheService->generateCacheKey('anthropic', $systemRole, $prompt);
        $cacheEntry = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertNotNull($cacheEntry);
        $this->assertEquals('anthropic', $cacheEntry->provider);

        // Second call - should use cache
        Http::fake([
            'api.anthropic.com/*' => Http::response('Should not be called', 500),
        ]);

        $result2 = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response, $result2);
    }

    public function test_different_prompts_create_different_cache_entries(): void
    {
        Config::set('ai-provider.cache.enabled', true);
        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai.api-key', 'test-key');

        $systemRole = 'You are a translator';
        $prompt1 = 'Translate hello to French';
        $prompt2 = 'Translate goodbye to French';
        $response1 = ['translation' => 'Bonjour'];
        $response2 = ['translation' => 'Au revoir'];

        Http::fake(function ($request) use ($prompt1, $response1, $response2) {
            $body = json_decode($request->body(), true);
            $userPrompt = $body['messages'][1]['content'][0]['text'] ?? '';

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode(
                                str_contains($userPrompt, 'hello') ? $response1 : $response2
                            )
                        ]
                    ]
                ]
            ], 200);
        });

        // Make two different calls
        $result1 = $this->service->prompt($systemRole, $prompt1);
        $result2 = $this->service->prompt($systemRole, $prompt2);

        $this->assertEquals($response1, $result1);
        $this->assertEquals($response2, $result2);

        // Verify two different cache entries were created
        $this->assertEquals(2, AiTranslationCache::count());

        $cacheKey1 = $this->cacheService->generateCacheKey('openai', $systemRole, $prompt1);
        $cacheKey2 = $this->cacheService->generateCacheKey('openai', $systemRole, $prompt2);

        $this->assertNotEquals($cacheKey1, $cacheKey2);
        $this->assertNotNull(AiTranslationCache::where('cache_key', $cacheKey1)->first());
        $this->assertNotNull(AiTranslationCache::where('cache_key', $cacheKey2)->first());
    }

    public function test_expired_cache_triggers_new_api_call(): void
    {
        Config::set('ai-provider.cache.enabled', true);
        Config::set('ai-provider.cache.ttl', 3600); // 1 hour
        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai.api-key', 'test-key');

        $systemRole = 'You are a translator';
        $prompt = 'Translate hello';
        $response1 = ['translation' => 'First'];
        $response2 = ['translation' => 'Second'];

        // Create an expired cache entry
        $cacheKey = $this->cacheService->generateCacheKey('openai', $systemRole, $prompt);
        $cache = AiTranslationCache::create([
            'cache_key' => $cacheKey,
            'provider' => 'openai',
            'system_prompt' => $systemRole,
            'user_prompt' => $prompt,
            'response' => $response1,
            'hits' => 5,
        ]);
        // Force it to be expired
        $cache->created_at = now()->subHours(2);
        $cache->save();

        // Mock new API response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($response2)
                        ]
                    ]
                ]
            ], 200),
        ]);

        // Call should trigger new API request since cache is expired
        $result = $this->service->prompt($systemRole, $prompt);
        $this->assertEquals($response2, $result);

        // Verify cache was updated
        $cache->refresh();
        $this->assertEquals($response2, $cache->response);
        $this->assertEquals(0, $cache->hits); // Hits should be reset
    }
}
