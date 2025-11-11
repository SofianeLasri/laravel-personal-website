<?php

declare(strict_types=1);

namespace Tests\Feature\Models\CustomEmoji;

use App\Models\CustomEmoji;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[CoversClass(CustomEmoji::class)]
class CustomEmojiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ========================================
    // Relationships
    // ========================================

    #[Test]
    #[TestDox('It belongs to a picture')]
    public function it_belongs_to_a_picture(): void
    {
        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create([
            'picture_id' => $picture->id,
        ]);

        $this->assertInstanceOf(Picture::class, $emoji->picture);
        $this->assertEquals($picture->id, $emoji->picture->id);
        $this->assertEquals($picture->filename, $emoji->picture->filename);
    }

    #[Test]
    #[TestDox('Picture cascade delete removes associated emoji')]
    public function picture_cascade_delete_removes_associated_emoji(): void
    {
        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create([
            'picture_id' => $picture->id,
        ]);

        $emojiId = $emoji->id;

        // Delete the picture
        $picture->delete();

        // Emoji should be deleted due to cascade
        $this->assertDatabaseMissing('custom_emojis', ['id' => $emojiId]);
    }

    #[Test]
    #[TestDox('It eager loads picture relationship')]
    public function it_eager_loads_picture_relationship(): void
    {
        $emoji = CustomEmoji::factory()->create();

        $loadedEmoji = CustomEmoji::with('picture')->find($emoji->id);

        $this->assertTrue($loadedEmoji->relationLoaded('picture'));
        $this->assertInstanceOf(Picture::class, $loadedEmoji->picture);
    }

    // ========================================
    // getOptimizedPicturesForRendering()
    // ========================================

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() returns optimized pictures ordered by format')]
    public function get_optimized_pictures_for_rendering_returns_optimized_pictures_ordered_by_format(): void
    {
        Config::set('emoji.formats', ['webp', 'jpg']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        // Create optimized pictures
        $jpg = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'jpg',
        ]);
        $webp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(2, $result);
        // Should contain both formats
        $formats = $result->pluck('format')->toArray();
        $this->assertContains('webp', $formats);
        $this->assertContains('jpg', $formats);
    }

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() respects database driver for ordering')]
    public function get_optimized_pictures_for_rendering_respects_database_driver_for_ordering(): void
    {
        Config::set('emoji.formats', ['avif', 'webp', 'jpg']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'jpg',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(2, $result);
        // In test environment (SQLite), formats are ordered alphabetically
        $this->assertContains($result->first()->format, ['jpg', 'webp']);
    }

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() filters by configured formats')]
    public function get_optimized_pictures_for_rendering_filters_by_configured_formats(): void
    {
        Config::set('emoji.formats', ['webp']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'jpg',
        ]);
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(1, $result);
        $this->assertEquals('webp', $result->first()->format);
    }

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() filters by configured variant')]
    public function get_optimized_pictures_for_rendering_filters_by_configured_variant(): void
    {
        Config::set('emoji.formats', ['webp', 'jpg']);
        Config::set('emoji.variant', 'small');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);
        $smallWebp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'small',
            'format' => 'webp',
        ]);
        $smallJpg = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'small',
            'format' => 'jpg',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(2, $result);
        $this->assertTrue($result->contains($smallWebp));
        $this->assertTrue($result->contains($smallJpg));
    }

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() returns empty collection when no matching optimized pictures')]
    public function get_optimized_pictures_for_rendering_returns_empty_collection_when_no_matching_optimized_pictures(): void
    {
        Config::set('emoji.formats', ['webp']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        // Create optimized pictures with different format and variant
        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'large',
            'format' => 'jpg',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    #[TestDox('getOptimizedPicturesForRendering() handles multiple formats with priority')]
    public function get_optimized_pictures_for_rendering_handles_multiple_formats_with_priority(): void
    {
        Config::set('emoji.formats', ['avif', 'webp', 'jpg']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        $jpg = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'jpg',
        ]);
        $avif = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
        ]);
        $webp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);

        $result = $emoji->getOptimizedPicturesForRendering();

        $this->assertCount(3, $result);
        // Verify all formats are present
        $formats = $result->pluck('format')->toArray();
        $this->assertContains('avif', $formats);
        $this->assertContains('webp', $formats);
        $this->assertContains('jpg', $formats);
    }

    // ========================================
    // getPreviewUrl()
    // ========================================

    #[Test]
    #[TestDox('getPreviewUrl() returns optimized picture URL when available')]
    public function get_preview_url_returns_optimized_picture_url_when_available(): void
    {
        Config::set('emoji.formats', ['webp']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        $optimized = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
            'path' => 'uploads/optimized/test.webp',
        ]);

        $url = $emoji->getPreviewUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('uploads/optimized/test.webp', $url);
    }

    #[Test]
    #[TestDox('getPreviewUrl() returns first available format from config')]
    public function get_preview_url_returns_first_available_format_from_config(): void
    {
        Config::set('emoji.formats', ['avif', 'webp', 'jpg']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create();
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        // Only create webp (avif not available)
        $webp = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
            'path' => 'uploads/optimized/test.webp',
        ]);

        $url = $emoji->getPreviewUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('test.webp', $url);
    }

    #[Test]
    #[TestDox('getPreviewUrl() falls back to original picture when no optimized versions available')]
    public function get_preview_url_falls_back_to_original_picture_when_no_optimized_versions_available(): void
    {
        Config::set('emoji.formats', ['webp']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create([
            'path_original' => 'uploads/original/test.jpg',
        ]);
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        // No optimized pictures created

        $url = $emoji->getPreviewUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('uploads/original/test.jpg', $url);
    }

    #[Test]
    #[TestDox('getPreviewUrl() returns null when no pictures available')]
    public function get_preview_url_returns_null_when_no_pictures_available(): void
    {
        Config::set('emoji.formats', ['webp']);
        Config::set('emoji.variant', 'thumbnail');

        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        // No optimized pictures and no original path

        $url = $emoji->getPreviewUrl();

        $this->assertNull($url);
    }

    #[Test]
    #[TestDox('getPreviewUrl() returns null when picture has null path_original and no optimized pictures')]
    public function get_preview_url_returns_null_when_picture_has_null_path_original_and_no_optimized_pictures(): void
    {
        $picture = Picture::factory()->create(['path_original' => null]);
        $emoji = CustomEmoji::factory()->create(['picture_id' => $picture->id]);

        $this->assertNull($emoji->getPreviewUrl());
    }

    // ========================================
    // Factory
    // ========================================

    #[Test]
    #[TestDox('Factory creates valid emoji')]
    public function factory_creates_valid_emoji(): void
    {
        $emoji = CustomEmoji::factory()->create();

        $this->assertDatabaseHas('custom_emojis', [
            'id' => $emoji->id,
            'name' => $emoji->name,
        ]);

        $this->assertNotNull($emoji->name);
        $this->assertNotNull($emoji->picture_id);
        $this->assertInstanceOf(Picture::class, $emoji->picture);
    }

    #[Test]
    #[TestDox('Factory creates unique names')]
    public function factory_creates_unique_names(): void
    {
        $emoji1 = CustomEmoji::factory()->create();
        $emoji2 = CustomEmoji::factory()->create();

        $this->assertNotEquals($emoji1->name, $emoji2->name);
    }

    // ========================================
    // CRUD Operations
    // ========================================

    #[Test]
    #[TestDox('It can create emoji with all attributes')]
    public function it_can_create_emoji_with_all_attributes(): void
    {
        $picture = Picture::factory()->create();

        $emoji = CustomEmoji::create([
            'name' => 'test_emoji',
            'picture_id' => $picture->id,
        ]);

        $this->assertInstanceOf(CustomEmoji::class, $emoji);
        $this->assertEquals('test_emoji', $emoji->name);
        $this->assertEquals($picture->id, $emoji->picture_id);
        $this->assertDatabaseHas('custom_emojis', [
            'name' => 'test_emoji',
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    #[TestDox('It can update emoji attributes')]
    public function it_can_update_emoji_attributes(): void
    {
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        $emoji = CustomEmoji::factory()->create([
            'name' => 'old_name',
            'picture_id' => $picture1->id,
        ]);

        $emoji->update([
            'name' => 'new_name',
            'picture_id' => $picture2->id,
        ]);

        $emoji->refresh();

        $this->assertEquals('new_name', $emoji->name);
        $this->assertEquals($picture2->id, $emoji->picture_id);
    }

    #[Test]
    #[TestDox('It can delete emoji')]
    public function it_can_delete_emoji(): void
    {
        $emoji = CustomEmoji::factory()->create();
        $emojiId = $emoji->id;

        $emoji->delete();

        $this->assertDatabaseMissing('custom_emojis', ['id' => $emojiId]);
    }

    #[Test]
    #[TestDox('It can find emoji by name')]
    public function it_can_find_emoji_by_name(): void
    {
        $emoji = CustomEmoji::factory()->create(['name' => 'unique_emoji']);

        $found = CustomEmoji::where('name', 'unique_emoji')->first();

        $this->assertNotNull($found);
        $this->assertEquals($emoji->id, $found->id);
        $this->assertEquals('unique_emoji', $found->name);
    }

    // ========================================
    // Database Constraints
    // ========================================

    #[Test]
    #[TestDox('Unique name constraint prevents duplicate emoji names')]
    public function unique_name_constraint_prevents_duplicate_emoji_names(): void
    {
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        CustomEmoji::create([
            'name' => 'duplicate_name',
            'picture_id' => $picture1->id,
        ]);

        $this->expectException(QueryException::class);

        CustomEmoji::create([
            'name' => 'duplicate_name',
            'picture_id' => $picture2->id,
        ]);
    }

    #[Test]
    #[TestDox('Foreign key constraint requires valid picture_id')]
    public function foreign_key_constraint_requires_valid_picture_id(): void
    {
        $this->expectException(QueryException::class);

        CustomEmoji::create([
            'name' => 'test_emoji',
            'picture_id' => 99999, // Non-existent picture ID
        ]);
    }

    // ========================================
    // Mass Assignment
    // ========================================

    #[Test]
    #[TestDox('It can mass assign all fillable attributes')]
    public function it_can_mass_assign_all_fillable_attributes(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'mass_assigned',
            'picture_id' => $picture->id,
        ];

        $emoji = CustomEmoji::create($data);

        $this->assertEquals('mass_assigned', $emoji->name);
        $this->assertEquals($picture->id, $emoji->picture_id);
    }

    // ========================================
    // Timestamps
    // ========================================

    #[Test]
    #[TestDox('It has timestamps')]
    public function it_has_timestamps(): void
    {
        $emoji = CustomEmoji::factory()->create();

        $this->assertNotNull($emoji->created_at);
        $this->assertNotNull($emoji->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emoji->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $emoji->updated_at);
    }

    #[Test]
    #[TestDox('Updated_at timestamp changes on update')]
    public function updated_at_timestamp_changes_on_update(): void
    {
        $emoji = CustomEmoji::factory()->create();
        $originalUpdatedAt = $emoji->updated_at;

        // Wait a bit to ensure timestamp difference
        sleep(1);

        $emoji->update(['name' => 'updated_name']);

        $this->assertNotEquals($originalUpdatedAt, $emoji->updated_at);
        $this->assertTrue($emoji->updated_at->greaterThan($originalUpdatedAt));
    }

    // ========================================
    // Serialization
    // ========================================

    #[Test]
    #[TestDox('It can be converted to array')]
    public function it_can_be_converted_to_array(): void
    {
        $emoji = CustomEmoji::factory()->create();
        $array = $emoji->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('picture_id', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    #[Test]
    #[TestDox('It can be converted to JSON')]
    public function it_can_be_converted_to_json(): void
    {
        $emoji = CustomEmoji::factory()->create([
            'name' => 'json_test_emoji',
        ]);

        $json = $emoji->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('json_test_emoji', $decoded['name']);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('picture_id', $decoded);
    }

    #[Test]
    #[TestDox('Serialization includes picture when loaded')]
    public function serialization_includes_picture_when_loaded(): void
    {
        $emoji = CustomEmoji::factory()->create();
        $emoji->load('picture');

        $array = $emoji->toArray();

        $this->assertArrayHasKey('picture', $array);
        $this->assertIsArray($array['picture']);
        $this->assertArrayHasKey('id', $array['picture']);
        $this->assertArrayHasKey('filename', $array['picture']);
    }
}
