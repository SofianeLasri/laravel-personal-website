<?php

namespace Tests\Unit\Services;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\BlogContentDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogContentDuplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogContentDuplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlogContentDuplicationService;
    }

    public function test_duplicates_markdown_content_with_translation_key()
    {
        // Create original markdown content with translation key
        $originalTranslationKey = TranslationKey::factory()->create(['key' => 'original.markdown']);
        $originalTranslationKey->translations()->create(['locale' => 'en', 'text' => 'Original English text']);
        $originalTranslationKey->translations()->create(['locale' => 'fr', 'text' => 'Texte français original']);

        $originalMarkdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $originalTranslationKey->id,
        ]);

        // Duplicate the markdown content
        $duplicatedMarkdown = $this->service->duplicateMarkdownContent($originalMarkdown);

        // Assert that we have a new markdown content with different ID
        $this->assertNotEquals($originalMarkdown->id, $duplicatedMarkdown->id);

        // Assert that the translation key was duplicated
        $this->assertNotEquals($originalTranslationKey->id, $duplicatedMarkdown->translation_key_id);

        // Assert that translations were copied
        $duplicatedTranslationKey = $duplicatedMarkdown->translationKey;
        $this->assertCount(2, $duplicatedTranslationKey->translations);
        $this->assertEquals('Original English text', $duplicatedTranslationKey->translations->where('locale', 'en')->first()->text);
        $this->assertEquals('Texte français original', $duplicatedTranslationKey->translations->where('locale', 'fr')->first()->text);

        // Assert that the key is different but based on original
        $this->assertStringContainsString('original.markdown', $duplicatedTranslationKey->key);
        $this->assertStringContainsString('copy', $duplicatedTranslationKey->key);
    }

    public function test_duplicates_gallery_content_with_pictures_and_captions()
    {
        // Create pictures
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        // Create caption translation keys
        $captionKey1 = TranslationKey::factory()->create(['key' => 'caption1']);
        $captionKey1->translations()->create(['locale' => 'en', 'text' => 'Caption 1']);

        $captionKey2 = TranslationKey::factory()->create(['key' => 'caption2']);
        $captionKey2->translations()->create(['locale' => 'en', 'text' => 'Caption 2']);

        // Create original gallery
        $originalGallery = BlogContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 3,
        ]);

        $originalGallery->pictures()->attach($picture1->id, [
            'order' => 1,
            'caption_translation_key_id' => $captionKey1->id,
        ]);

        $originalGallery->pictures()->attach($picture2->id, [
            'order' => 2,
            'caption_translation_key_id' => $captionKey2->id,
        ]);

        // Duplicate the gallery
        $duplicatedGallery = $this->service->duplicateGalleryContent($originalGallery);

        // Assert gallery was duplicated
        $this->assertNotEquals($originalGallery->id, $duplicatedGallery->id);
        $this->assertEquals('grid', $duplicatedGallery->layout);
        $this->assertEquals(3, $duplicatedGallery->columns);

        // Assert pictures are attached with correct pivot data
        $this->assertCount(2, $duplicatedGallery->pictures);

        $duplicatedPicture1 = $duplicatedGallery->pictures()->where('picture_id', $picture1->id)->first();
        $duplicatedPicture2 = $duplicatedGallery->pictures()->where('picture_id', $picture2->id)->first();

        $this->assertEquals(1, $duplicatedPicture1->pivot->order);
        $this->assertEquals(2, $duplicatedPicture2->pivot->order);

        // Assert caption translation keys were duplicated
        $this->assertNotEquals($captionKey1->id, $duplicatedPicture1->pivot->caption_translation_key_id);
        $this->assertNotEquals($captionKey2->id, $duplicatedPicture2->pivot->caption_translation_key_id);

        // Assert caption translations were copied
        $newCaptionKey1 = TranslationKey::find($duplicatedPicture1->pivot->caption_translation_key_id);
        $newCaptionKey2 = TranslationKey::find($duplicatedPicture2->pivot->caption_translation_key_id);

        $this->assertEquals('Caption 1', $newCaptionKey1->translations->first()->text);
        $this->assertEquals('Caption 2', $newCaptionKey2->translations->first()->text);
    }

    public function test_duplicates_video_content_with_caption()
    {
        // Create video and caption translation key
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create(['key' => 'video.caption']);
        $captionKey->translations()->create(['locale' => 'en', 'text' => 'Video caption']);

        // Create original video content
        $originalVideoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        // Duplicate the video content
        $duplicatedVideoContent = $this->service->duplicateVideoContent($originalVideoContent);

        // Assert video content was duplicated
        $this->assertNotEquals($originalVideoContent->id, $duplicatedVideoContent->id);
        $this->assertEquals($video->id, $duplicatedVideoContent->video_id);

        // Assert caption translation key was duplicated
        $this->assertNotEquals($captionKey->id, $duplicatedVideoContent->caption_translation_key_id);

        // Assert caption translation was copied
        $newCaptionKey = $duplicatedVideoContent->captionTranslationKey;
        $this->assertEquals('Video caption', $newCaptionKey->translations->first()->text);
        $this->assertStringContainsString('video.caption', $newCaptionKey->key);
        $this->assertStringContainsString('copy', $newCaptionKey->key);
    }

    public function test_duplicates_video_content_without_caption()
    {
        // Create video content without caption
        $video = Video::factory()->create();
        $originalVideoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);

        // Duplicate the video content
        $duplicatedVideoContent = $this->service->duplicateVideoContent($originalVideoContent);

        // Assert video content was duplicated
        $this->assertNotEquals($originalVideoContent->id, $duplicatedVideoContent->id);
        $this->assertEquals($video->id, $duplicatedVideoContent->video_id);
        $this->assertNull($duplicatedVideoContent->caption_translation_key_id);
    }

    public function test_generates_unique_translation_keys()
    {
        // Create multiple translation keys with similar names
        TranslationKey::factory()->create(['key' => 'unique.key']);
        TranslationKey::factory()->create(['key' => 'unique.key_copy']);
        TranslationKey::factory()->create(['key' => 'unique.key_copy_1']);

        $originalKey = TranslationKey::factory()->create(['key' => 'unique.key.original']);
        $originalMarkdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $originalKey->id,
        ]);

        // Duplicate should create unique key
        $duplicated = $this->service->duplicateMarkdownContent($originalMarkdown);
        $newKey = $duplicated->translationKey;

        $this->assertStringContainsString('unique.key.original', $newKey->key);
        $this->assertStringContainsString('copy', $newKey->key);
    }
}
