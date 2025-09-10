<?php

namespace Tests\Unit;

use App\Models\AiTranslationCache;
use App\Services\AiTranslationCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiTranslationCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiTranslationCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiTranslationCacheService;
    }

    public function test_generate_cache_key_creates_consistent_hash(): void
    {
        $provider = 'openai';
        $systemPrompt = 'You are a helpful assistant';
        $userPrompt = 'Translate this text';

        $key1 = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);
        $key2 = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        $this->assertEquals($key1, $key2);
        $this->assertEquals(64, strlen($key1));
    }

    public function test_generate_cache_key_creates_different_hash_for_different_inputs(): void
    {
        $key1 = $this->service->generateCacheKey('openai', 'prompt1', 'user1');
        $key2 = $this->service->generateCacheKey('anthropic', 'prompt1', 'user1');
        $key3 = $this->service->generateCacheKey('openai', 'prompt2', 'user1');
        $key4 = $this->service->generateCacheKey('openai', 'prompt1', 'user2');

        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertNotEquals($key1, $key4);
    }

    public function test_put_stores_cache_entry(): void
    {
        $provider = 'openai';
        $systemPrompt = 'You are a translator';
        $userPrompt = 'Translate to French';
        $response = ['translation' => 'Bonjour'];

        $result = $this->service->put($provider, $systemPrompt, $userPrompt, $response);

        $this->assertTrue($result);

        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);
        $cache = AiTranslationCache::where('cache_key', $cacheKey)->first();

        $this->assertNotNull($cache);
        $this->assertEquals($provider, $cache->provider);
        $this->assertEquals($systemPrompt, $cache->system_prompt);
        $this->assertEquals($userPrompt, $cache->user_prompt);
        $this->assertEquals($response, $cache->response);
        $this->assertEquals(0, $cache->hits);
    }

    public function test_get_retrieves_cached_response(): void
    {
        $provider = 'openai';
        $systemPrompt = 'System prompt';
        $userPrompt = 'User prompt';
        $response = ['result' => 'test'];
        $ttl = 3600;

        $this->service->put($provider, $systemPrompt, $userPrompt, $response);
        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        $cached = $this->service->get($cacheKey, $ttl);

        $this->assertNotNull($cached);
        $this->assertEquals($response, $cached);

        $cache = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertEquals(1, $cache->hits);
    }

    public function test_get_returns_null_for_non_existent_cache(): void
    {
        $cacheKey = 'non-existent-key';
        $cached = $this->service->get($cacheKey, 3600);

        $this->assertNull($cached);
    }

    public function test_get_returns_null_for_expired_cache(): void
    {
        $provider = 'openai';
        $systemPrompt = 'System';
        $userPrompt = 'User';
        $response = ['test' => 'data'];

        $this->service->put($provider, $systemPrompt, $userPrompt, $response);
        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        // Update created_at to be expired
        AiTranslationCache::where('cache_key', $cacheKey)
            ->update(['created_at' => now()->subSeconds(3601)]);

        $cached = $this->service->get($cacheKey, 3600);

        $this->assertNull($cached);
    }

    public function test_clear_expired_removes_old_entries(): void
    {
        // Create old entry - older than 30 days
        $oldEntry = AiTranslationCache::create([
            'cache_key' => 'old-key',
            'provider' => 'openai',
            'system_prompt' => 'old',
            'user_prompt' => 'old',
            'response' => ['old' => 'data'],
            'hits' => 5,
        ]);
        // Force the created_at to be 31 days ago
        $oldEntry->created_at = now()->subDays(31);
        $oldEntry->save();

        // Create recent entry
        AiTranslationCache::create([
            'cache_key' => 'new-key',
            'provider' => 'openai',
            'system_prompt' => 'new',
            'user_prompt' => 'new',
            'response' => ['new' => 'data'],
            'hits' => 0,
        ]);

        $ttl = 30 * 24 * 60 * 60; // 30 days
        $deleted = $this->service->clearExpired($ttl);

        $this->assertEquals(1, $deleted);
        $this->assertNull(AiTranslationCache::where('cache_key', 'old-key')->first());
        $this->assertNotNull(AiTranslationCache::where('cache_key', 'new-key')->first());
    }

    public function test_clear_all_removes_all_entries(): void
    {
        // Create multiple entries
        for ($i = 0; $i < 3; $i++) {
            AiTranslationCache::create([
                'cache_key' => "key-$i",
                'provider' => 'openai',
                'system_prompt' => "system-$i",
                'user_prompt' => "user-$i",
                'response' => ['data' => $i],
                'hits' => $i,
            ]);
        }

        $this->assertEquals(3, AiTranslationCache::count());

        $deleted = $this->service->clearAll();

        $this->assertEquals(3, $deleted);
        $this->assertEquals(0, AiTranslationCache::count());
    }

    public function test_forget_removes_specific_entry(): void
    {
        $provider = 'openai';
        $systemPrompt = 'System';
        $userPrompt = 'User';
        $response = ['data' => 'test'];

        $this->service->put($provider, $systemPrompt, $userPrompt, $response);
        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        $this->assertNotNull(AiTranslationCache::where('cache_key', $cacheKey)->first());

        $result = $this->service->forget($cacheKey);

        $this->assertTrue($result);
        $this->assertNull(AiTranslationCache::where('cache_key', $cacheKey)->first());
    }

    public function test_forget_returns_false_for_non_existent_key(): void
    {
        $result = $this->service->forget('non-existent-key');
        $this->assertFalse($result);
    }

    public function test_get_statistics_returns_correct_data(): void
    {
        // Create test data
        AiTranslationCache::create([
            'cache_key' => 'key1',
            'provider' => 'openai',
            'system_prompt' => 'sys1',
            'user_prompt' => 'user1',
            'response' => ['data' => 1],
            'hits' => 10,
            'created_at' => now()->subDays(5),
        ]);

        AiTranslationCache::create([
            'cache_key' => 'key2',
            'provider' => 'anthropic',
            'system_prompt' => 'sys2',
            'user_prompt' => 'user2',
            'response' => ['data' => 2],
            'hits' => 5,
            'created_at' => now()->subDays(2),
        ]);

        AiTranslationCache::create([
            'cache_key' => 'key3',
            'provider' => 'openai',
            'system_prompt' => 'sys3',
            'user_prompt' => 'user3',
            'response' => ['data' => 3],
            'hits' => 15,
            'created_at' => now(),
        ]);

        $stats = $this->service->getStatistics();

        $this->assertEquals(3, $stats['total_entries']);
        $this->assertEquals(30, $stats['total_hits']);
        $this->assertEquals(10, $stats['average_hits']);
        $this->assertCount(3, $stats['most_used']);
        $this->assertEquals('key3', $stats['most_used'][0]['cache_key']);
        $this->assertCount(2, $stats['providers']);
    }

    public function test_is_enabled_respects_configuration(): void
    {
        Config::set('ai-provider.cache.enabled', true);
        $this->assertTrue($this->service->isEnabled());

        Config::set('ai-provider.cache.enabled', false);
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_get_ttl_returns_configuration_value(): void
    {
        Config::set('ai-provider.cache.ttl', 7200);
        $this->assertEquals(7200, $this->service->getTtl());

        Config::set('ai-provider.cache.ttl', null);
        $this->assertEquals(2592000, $this->service->getTtl()); // Default 30 days
    }

    public function test_update_existing_cache_entry(): void
    {
        $provider = 'openai';
        $systemPrompt = 'System';
        $userPrompt = 'User';
        $response1 = ['version' => 1];
        $response2 = ['version' => 2];

        $this->service->put($provider, $systemPrompt, $userPrompt, $response1);
        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        $cache1 = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertEquals($response1, $cache1->response);

        $this->service->put($provider, $systemPrompt, $userPrompt, $response2);

        $cache2 = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertEquals($response2, $cache2->response);
        $this->assertEquals(0, $cache2->hits); // Hits should be reset on update
        $this->assertEquals(1, AiTranslationCache::where('cache_key', $cacheKey)->count());
    }

    public function test_increment_hits_works_correctly(): void
    {
        $provider = 'openai';
        $systemPrompt = 'System';
        $userPrompt = 'User';
        $response = ['data' => 'test'];
        $ttl = 3600;

        $this->service->put($provider, $systemPrompt, $userPrompt, $response);
        $cacheKey = $this->service->generateCacheKey($provider, $systemPrompt, $userPrompt);

        // Get multiple times to increment hits
        for ($i = 0; $i < 3; $i++) {
            $this->service->get($cacheKey, $ttl);
        }

        $cache = AiTranslationCache::where('cache_key', $cacheKey)->first();
        $this->assertEquals(3, $cache->hits);
    }
}
