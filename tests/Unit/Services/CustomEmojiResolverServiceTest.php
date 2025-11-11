<?php

namespace Tests\Unit\Services;

use App\Models\CustomEmoji;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Services\CustomEmojiResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CustomEmojiResolverService::class)]
class CustomEmojiResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomEmojiResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = new CustomEmojiResolverService;
    }

    #[Test]
    public function test_resolves_emoji_in_markdown()
    {
        $picture = Picture::factory()->create();

        // Create optimized pictures
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/test.webp',
        ]);

        $emoji = CustomEmoji::factory()->create([
            'name' => 'test_emoji',
            'picture_id' => $picture->id,
        ]);

        $markdown = 'Hello :test_emoji: world!';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringContainsString('<picture class="inline-emoji">', $resolved);
        $this->assertStringContainsString('alt="test_emoji"', $resolved);
        $this->assertStringNotContainsString(':test_emoji:', $resolved);
    }

    #[Test]
    public function test_resolves_multiple_emojis_in_markdown()
    {
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        $emoji1 = CustomEmoji::factory()->create(['name' => 'smile', 'picture_id' => $picture1->id]);
        $emoji2 = CustomEmoji::factory()->create(['name' => 'heart', 'picture_id' => $picture2->id]);

        $markdown = 'I am :smile: and I love :heart: emojis!';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringContainsString('alt="smile"', $resolved);
        $this->assertStringContainsString('alt="heart"', $resolved);
        $this->assertStringNotContainsString(':smile:', $resolved);
        $this->assertStringNotContainsString(':heart:', $resolved);
    }

    #[Test]
    public function test_leaves_unknown_emoji_unchanged()
    {
        $markdown = 'Hello :nonexistent_emoji: world!';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        // Should remain unchanged
        $this->assertEquals($markdown, $resolved);
        $this->assertStringContainsString(':nonexistent_emoji:', $resolved);
    }

    #[Test]
    public function test_returns_unchanged_when_no_emoji_patterns()
    {
        $markdown = 'This is just plain text without any emojis.';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertEquals($markdown, $resolved);
    }

    #[Test]
    public function test_handles_emoji_with_underscores_and_numbers()
    {
        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create([
            'name' => 'emoji_test_123',
            'picture_id' => $picture->id,
        ]);

        $markdown = 'Test :emoji_test_123: here';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringContainsString('alt="emoji_test_123"', $resolved);
    }

    #[Test]
    public function test_resolves_emojis_in_batch()
    {
        $picture = Picture::factory()->create();

        // Create optimized picture
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/test.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'test', 'picture_id' => $picture->id]);

        $markdownTexts = [
            'first' => 'First :test: text',
            'second' => 'Second :test: text',
            'third' => 'Plain text',
        ];

        $resolved = $this->service->resolveEmojisInBatch($markdownTexts);

        $this->assertCount(3, $resolved);
        $this->assertStringContainsString('<picture', $resolved['first']);
        $this->assertStringContainsString('<picture', $resolved['second']);
        $this->assertEquals('Plain text', $resolved['third']);
    }

    #[Test]
    public function test_caching_works_correctly()
    {
        // Clear cache first
        Cache::forget('custom_emojis_all');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['name' => 'cached', 'picture_id' => $picture->id]);

        // First call should cache
        $markdown = 'Test :cached: emoji';
        $resolved1 = $this->service->resolveEmojisInMarkdown($markdown);

        // Verify cache was set
        $this->assertTrue(Cache::has('custom_emojis_all'));

        // Second call should use cache
        $resolved2 = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertEquals($resolved1, $resolved2);
    }

    #[Test]
    public function test_clear_cache_removes_emoji_cache()
    {
        // Set up cache first
        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['name' => 'test', 'picture_id' => $picture->id]);

        $this->service->resolveEmojisInMarkdown(':test:');

        // Verify cache exists
        $this->assertTrue(Cache::has('custom_emojis_all'));

        // Clear cache
        CustomEmojiResolverService::clearCache();

        // Verify cache was cleared
        $this->assertFalse(Cache::has('custom_emojis_all'));
    }

    #[Test]
    public function test_handles_emoji_at_start_of_text()
    {
        $picture = Picture::factory()->create();

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/start.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'start', 'picture_id' => $picture->id]);

        $markdown = ':start: at the beginning';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringStartsWith('<picture', $resolved);
    }

    #[Test]
    public function test_handles_emoji_at_end_of_text()
    {
        $picture = Picture::factory()->create();

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/end.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'end', 'picture_id' => $picture->id]);

        $markdown = 'At the end :end:';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringEndsWith('</picture>', $resolved);
    }

    #[Test]
    public function test_handles_consecutive_emojis()
    {
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        $emoji1 = CustomEmoji::factory()->create(['name' => 'one', 'picture_id' => $picture1->id]);
        $emoji2 = CustomEmoji::factory()->create(['name' => 'two', 'picture_id' => $picture2->id]);

        $markdown = ':one::two:';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        $this->assertStringContainsString('alt="one"', $resolved);
        $this->assertStringContainsString('alt="two"', $resolved);
    }

    #[Test]
    public function test_handles_empty_string()
    {
        $resolved = $this->service->resolveEmojisInMarkdown('');

        $this->assertEquals('', $resolved);
    }

    #[Test]
    public function test_resolves_emoji_with_optimized_pictures()
    {
        $picture = Picture::factory()->create();

        // Create optimized pictures with configured formats
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/test.webp',
        ]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'jpg',
            'variant' => 'thumbnail',
            'path' => 'optimized/test.jpg',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'optimized', 'picture_id' => $picture->id]);

        $markdown = 'Test :optimized: here';

        $resolved = $this->service->resolveEmojisInMarkdown($markdown);

        // Should contain picture tag with source elements
        $this->assertStringContainsString('<picture class="inline-emoji">', $resolved);
        $this->assertStringContainsString('<source', $resolved);
        $this->assertStringContainsString('type="image/webp"', $resolved);
        $this->assertStringContainsString('type="image/jpeg"', $resolved);
    }
}
