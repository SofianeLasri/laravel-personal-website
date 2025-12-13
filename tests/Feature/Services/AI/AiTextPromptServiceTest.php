<?php

declare(strict_types=1);

namespace Tests\Feature\Services\AI;

use App\Models\AiTranslationCache;
use App\Services\AI\AiApiClientService;
use App\Services\AI\AiTextPromptService;
use App\Services\AiTranslationCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AiTextPromptService::class)]
class AiTextPromptServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiTextPromptService $service;

    private AiApiClientService|MockInterface $mockApiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiClient = Mockery::mock(AiApiClientService::class);
        $this->app->instance(AiApiClientService::class, $this->mockApiClient);

        $this->service = new AiTextPromptService(
            $this->mockApiClient,
            app(AiTranslationCacheService::class)
        );
    }

    #[Test]
    public function it_calls_openai_api_when_openai_provider_selected(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => false]);

        $expectedResponse = ['message' => 'Hello from OpenAI'];

        $this->mockApiClient
            ->shouldReceive('callOpenAi')
            ->once()
            ->andReturn($expectedResponse);

        $result = $this->service->prompt('You are a helpful assistant', 'Say hello');

        $this->assertEquals($expectedResponse, $result);
    }

    #[Test]
    public function it_calls_anthropic_api_when_anthropic_provider_selected(): void
    {
        config(['ai-provider.selected-provider' => 'anthropic']);
        config(['ai-provider.providers.anthropic' => [
            'model' => 'claude-3',
            'url' => 'https://api.anthropic.com/v1/messages',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => false]);

        $expectedResponse = ['message' => 'Hello from Anthropic'];

        $this->mockApiClient
            ->shouldReceive('callAnthropic')
            ->once()
            ->andReturn($expectedResponse);

        $result = $this->service->prompt('You are a helpful assistant', 'Say hello');

        $this->assertEquals($expectedResponse, $result);
    }

    #[Test]
    public function it_returns_cached_response_when_cache_hit(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => true]);
        config(['ai-provider.cache.ttl' => 3600]);

        $systemRole = 'You are a helpful assistant';
        $prompt = 'Say hello';
        $cachedResponse = ['message' => 'Cached response'];

        // Create cache entry
        $cacheService = app(AiTranslationCacheService::class);
        $cacheService->put('openai', $systemRole, $prompt, $cachedResponse);

        // API should not be called
        $this->mockApiClient->shouldNotReceive('callOpenAi');
        $this->mockApiClient->shouldNotReceive('callAnthropic');

        $result = $this->service->prompt($systemRole, $prompt);

        $this->assertEquals($cachedResponse, $result);
    }

    #[Test]
    public function it_caches_response_when_cache_enabled(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => true]);
        config(['ai-provider.cache.ttl' => 3600]);

        $systemRole = 'You are a helpful assistant';
        $prompt = 'Say hello';
        $apiResponse = ['message' => 'API response'];

        $this->mockApiClient
            ->shouldReceive('callOpenAi')
            ->once()
            ->andReturn($apiResponse);

        $this->service->prompt($systemRole, $prompt);

        // Verify cache was created
        $cacheService = app(AiTranslationCacheService::class);
        $cacheKey = $cacheService->generateCacheKey('openai', $systemRole, $prompt);
        $cached = AiTranslationCache::where('cache_key', $cacheKey)->first();

        $this->assertNotNull($cached);
        $this->assertEquals($apiResponse, $cached->response);
    }

    #[Test]
    public function it_bypasses_cache_when_cache_disabled(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => false]);

        $apiResponse = ['message' => 'API response'];

        $this->mockApiClient
            ->shouldReceive('callOpenAi')
            ->once()
            ->andReturn($apiResponse);

        $result = $this->service->prompt('You are a helpful assistant', 'Say hello');

        $this->assertEquals($apiResponse, $result);
        $this->assertEquals(0, AiTranslationCache::count());
    }

    #[Test]
    public function it_does_not_cache_when_cache_disabled_even_with_existing_cache(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => false]);

        $systemRole = 'You are a helpful assistant';
        $prompt = 'Say hello';
        $cachedResponse = ['message' => 'Cached response'];
        $apiResponse = ['message' => 'Fresh API response'];

        // Create cache entry (would be from before cache was disabled)
        AiTranslationCache::create([
            'cache_key' => 'test-key',
            'provider' => 'openai',
            'system_prompt' => $systemRole,
            'user_prompt' => $prompt,
            'response' => $cachedResponse,
            'hits' => 0,
        ]);

        // API should be called even though cache exists (cache is disabled)
        $this->mockApiClient
            ->shouldReceive('callOpenAi')
            ->once()
            ->andReturn($apiResponse);

        $result = $this->service->prompt($systemRole, $prompt);

        $this->assertEquals($apiResponse, $result);
    }

    #[Test]
    public function it_increments_cache_hits_on_cache_hit(): void
    {
        config(['ai-provider.selected-provider' => 'openai']);
        config(['ai-provider.providers.openai' => [
            'model' => 'gpt-4',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ]]);
        config(['ai-provider.cache.enabled' => true]);
        config(['ai-provider.cache.ttl' => 3600]);

        $systemRole = 'You are a helpful assistant';
        $prompt = 'Say hello';

        // Create cache entry
        $cacheService = app(AiTranslationCacheService::class);
        $cacheService->put('openai', $systemRole, $prompt, ['message' => 'Cached']);

        $this->mockApiClient->shouldNotReceive('callOpenAi');
        $this->mockApiClient->shouldNotReceive('callAnthropic');

        // First hit
        $this->service->prompt($systemRole, $prompt);

        $cacheKey = $cacheService->generateCacheKey('openai', $systemRole, $prompt);
        $cached = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertEquals(1, $cached->hits);

        // Second hit
        $this->service->prompt($systemRole, $prompt);

        $cached->refresh();
        $this->assertEquals(2, $cached->hits);
    }
}
