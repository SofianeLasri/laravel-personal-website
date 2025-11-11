<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CustomEmojiHelper;
use App\Models\CustomEmoji;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CustomEmojiHelper::class)]
class CustomEmojiHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Set default config values
        Config::set('emoji.formats', ['webp', 'jpg']);
        Config::set('emoji.variant', 'thumbnail');
    }

    #[Test]
    public function test_generates_picture_tag_with_optimized_pictures()
    {
        $picture = Picture::factory()->create(['path_original' => 'uploads/emoji.png']);

        $optimizedWebp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/emoji.webp',
        ]);

        $optimizedJpg = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'jpg',
            'variant' => 'thumbnail',
            'path' => 'optimized/emoji.jpg',
        ]);

        $emoji = CustomEmoji::factory()->create([
            'name' => 'test_emoji',
            'picture_id' => $picture->id,
        ]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Assert picture tag structure
        $this->assertStringContainsString('<picture class="inline-emoji">', $html);
        $this->assertStringContainsString('</picture>', $html);

        // Assert source tags for both formats
        $this->assertStringContainsString('<source srcset=', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('type="image/jpeg"', $html);

        // Assert img fallback tag
        $this->assertStringContainsString('<img src=', $html);
        $this->assertStringContainsString('alt="test_emoji"', $html);
        $this->assertStringContainsString('class="inline-emoji"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    #[Test]
    public function test_generates_picture_tag_with_only_webp()
    {
        Config::set('emoji.formats', ['webp']);

        $picture = Picture::factory()->create(['path_original' => 'uploads/emoji.png']);

        $optimizedWebp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/emoji.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'webp_only', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringNotContainsString('type="image/jpeg"', $html);
    }

    #[Test]
    public function test_fallback_to_original_when_no_optimized_pictures()
    {
        $picture = Picture::factory()->create(['path_original' => 'uploads/original.png']);

        $emoji = CustomEmoji::factory()->create(['name' => 'no_optimized', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Should generate simple img tag with original
        $this->assertStringContainsString('<img src=', $html);
        $this->assertStringContainsString('alt="no_optimized"', $html);
        $this->assertStringContainsString('class="inline-emoji"', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('<source', $html);
    }

    #[Test]
    public function test_returns_emoji_name_when_no_pictures_available()
    {
        $picture = Picture::factory()->create(['path_original' => null]);

        $emoji = CustomEmoji::factory()->create(['name' => 'no_picture', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Should return the emoji name in colon syntax
        $this->assertEquals(':no_picture:', $html);
    }

    #[Test]
    public function test_escapes_html_in_emoji_name()
    {
        $picture = Picture::factory()->create(['path_original' => 'uploads/xss.png']);

        $emoji = CustomEmoji::factory()->create([
            'name' => 'test<script>alert("xss")</script>',
            'picture_id' => $picture->id,
        ]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Should escape the HTML in alt attribute
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function test_respects_configured_variant()
    {
        Config::set('emoji.variant', 'small');

        $picture = Picture::factory()->create();

        // Create optimized pictures with different variants
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/thumbnail.webp',
        ]);

        $optimizedSmall = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'small',
            'path' => 'optimized/small.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'sized', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Should use the 'small' size, not 'thumbnail'
        $this->assertStringContainsString('small.webp', $html);
        $this->assertStringNotContainsString('thumbnail.webp', $html);
    }

    #[Test]
    public function test_orders_formats_correctly()
    {
        Config::set('emoji.formats', ['jpg', 'webp']); // JPG first

        $picture = Picture::factory()->create();

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/emoji.webp',
        ]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'jpg',
            'variant' => 'thumbnail',
            'path' => 'optimized/emoji.jpg',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'ordered', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // JPG should appear before WebP in the HTML (as per config order)
        $jpgPos = strpos($html, 'type="image/jpeg"');
        $webpPos = strpos($html, 'type="image/webp"');

        $this->assertNotFalse($jpgPos);
        $this->assertNotFalse($webpPos);
        $this->assertLessThan($webpPos, $jpgPos);
    }

    #[Test]
    public function test_get_mime_type_returns_correct_types()
    {
        $reflection = new \ReflectionClass(CustomEmojiHelper::class);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $this->assertEquals('image/webp', $method->invoke(null, 'webp'));
        $this->assertEquals('image/avif', $method->invoke(null, 'avif'));
        $this->assertEquals('image/png', $method->invoke(null, 'png'));
        $this->assertEquals('image/jpeg', $method->invoke(null, 'jpg'));
        $this->assertEquals('image/jpeg', $method->invoke(null, 'jpeg'));
        $this->assertEquals('image/gif', $method->invoke(null, 'gif'));
        $this->assertEquals('image/svg+xml', $method->invoke(null, 'svg'));
        $this->assertEquals('image/unknown', $method->invoke(null, 'unknown'));
    }

    #[Test]
    public function test_handles_special_characters_in_paths()
    {
        $picture = Picture::factory()->create(['path_original' => 'uploads/emoji with spaces.png']);

        $emoji = CustomEmoji::factory()->create(['name' => 'spaces', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Should properly encode the URL
        $this->assertStringContainsString('emoji', $html);
    }

    #[Test]
    public function test_generates_valid_html_structure()
    {
        $picture = Picture::factory()->create(['path_original' => 'uploads/test.png']);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'format' => 'webp',
            'variant' => 'thumbnail',
            'path' => 'optimized/test.webp',
        ]);

        $emoji = CustomEmoji::factory()->create(['name' => 'valid', 'picture_id' => $picture->id]);

        $html = CustomEmojiHelper::generatePictureTag($emoji);

        // Check for proper nesting: picture > source + img
        $this->assertMatchesRegularExpression(
            '/<picture[^>]*>.*<source[^>]*>.*<img[^>]*>.*<\/picture>/s',
            $html
        );
    }
}
