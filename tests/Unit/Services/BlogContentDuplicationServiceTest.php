<?php

namespace Tests\Unit\Services;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\BlogContentDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BlogContentDuplicationService::class)]
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

        $originalMarkdown = ContentMarkdown::factory()->create([
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
        $originalGallery = ContentGallery::factory()->create([
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
        $originalVideoContent = ContentVideo::factory()->create([
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
        $originalVideoContent = ContentVideo::factory()->create([
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
        $originalMarkdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $originalKey->id,
        ]);

        // Duplicate should create unique key
        $duplicated = $this->service->duplicateMarkdownContent($originalMarkdown);
        $newKey = $duplicated->translationKey;

        $this->assertStringContainsString('unique.key.original', $newKey->key);
        $this->assertStringContainsString('copy', $newKey->key);
    }

    public function test_duplicates_all_contents_with_mixed_types()
    {
        // Create markdown content
        $markdownTranslationKey = TranslationKey::factory()->create(['key' => 'markdown.content']);
        $markdownTranslationKey->translations()->create(['locale' => 'en', 'text' => 'Markdown text']);
        $markdownContent = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownTranslationKey->id,
        ]);

        // Create gallery content
        $picture = Picture::factory()->create();
        $galleryContent = ContentGallery::factory()->create([
            'layout' => 'masonry',
            'columns' => 2,
        ]);
        $galleryContent->pictures()->attach($picture->id, ['order' => 1]);

        // Create video content
        $video = Video::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        // Create blog post draft contents to simulate the structure
        $blogPostDraft = \App\Models\BlogPostDraft::factory()->create();
        $contents = collect([
            (object) [
                'content_type' => ContentMarkdown::class,
                'content' => $markdownContent,
                'order' => 1,
            ],
            (object) [
                'content_type' => ContentGallery::class,
                'content' => $galleryContent,
                'order' => 2,
            ],
            (object) [
                'content_type' => ContentVideo::class,
                'content' => $videoContent,
                'order' => 3,
            ],
        ]);

        // Duplicate all contents
        $newContents = $this->service->duplicateAllContents($contents);

        // Assert we have 3 new contents
        $this->assertCount(3, $newContents);

        // Assert markdown content was duplicated correctly
        $this->assertEquals(ContentMarkdown::class, $newContents[0]['content_type']);
        $this->assertEquals(1, $newContents[0]['order']);
        $markdownDuplicate = ContentMarkdown::find($newContents[0]['content_id']);
        $this->assertNotNull($markdownDuplicate);
        $this->assertNotEquals($markdownContent->id, $markdownDuplicate->id);

        // Assert gallery content was duplicated correctly
        $this->assertEquals(ContentGallery::class, $newContents[1]['content_type']);
        $this->assertEquals(2, $newContents[1]['order']);
        $galleryDuplicate = ContentGallery::find($newContents[1]['content_id']);
        $this->assertNotNull($galleryDuplicate);
        $this->assertNotEquals($galleryContent->id, $galleryDuplicate->id);

        // Assert video content was duplicated correctly
        $this->assertEquals(ContentVideo::class, $newContents[2]['content_type']);
        $this->assertEquals(3, $newContents[2]['order']);
        $videoDuplicate = ContentVideo::find($newContents[2]['content_id']);
        $this->assertNotNull($videoDuplicate);
        $this->assertNotEquals($videoContent->id, $videoDuplicate->id);
    }

    public function test_duplicates_all_contents_skips_unknown_content_type()
    {
        // Create markdown content
        $markdownTranslationKey = TranslationKey::factory()->create(['key' => 'markdown.content']);
        $markdownContent = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownTranslationKey->id,
        ]);

        // Create contents collection with an unknown content type
        $contents = collect([
            (object) [
                'content_type' => ContentMarkdown::class,
                'content' => $markdownContent,
                'order' => 1,
            ],
            (object) [
                'content_type' => 'App\Models\UnknownContentType', // Unknown type
                'content' => null,
                'order' => 2,
            ],
        ]);

        // Duplicate all contents
        $newContents = $this->service->duplicateAllContents($contents);

        // Assert only the markdown content was duplicated (unknown type skipped)
        $this->assertCount(1, $newContents);
        $this->assertEquals(ContentMarkdown::class, $newContents[0]['content_type']);
        $this->assertEquals(1, $newContents[0]['order']);
    }

    public function test_duplicates_gallery_without_captions()
    {
        // Create pictures
        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        // Create gallery without captions
        $originalGallery = ContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 2,
        ]);

        $originalGallery->pictures()->attach($picture1->id, [
            'order' => 1,
            'caption_translation_key_id' => null, // No caption
        ]);

        $originalGallery->pictures()->attach($picture2->id, [
            'order' => 2,
            'caption_translation_key_id' => null, // No caption
        ]);

        // Duplicate the gallery
        $duplicatedGallery = $this->service->duplicateGalleryContent($originalGallery);

        // Assert gallery was duplicated
        $this->assertNotEquals($originalGallery->id, $duplicatedGallery->id);
        $this->assertEquals('grid', $duplicatedGallery->layout);
        $this->assertEquals(2, $duplicatedGallery->columns);

        // Assert pictures are attached without captions
        $this->assertCount(2, $duplicatedGallery->pictures);

        $duplicatedPicture1 = $duplicatedGallery->pictures()->where('picture_id', $picture1->id)->first();
        $duplicatedPicture2 = $duplicatedGallery->pictures()->where('picture_id', $picture2->id)->first();

        $this->assertEquals(1, $duplicatedPicture1->pivot->order);
        $this->assertEquals(2, $duplicatedPicture2->pivot->order);
        $this->assertNull($duplicatedPicture1->pivot->caption_translation_key_id);
        $this->assertNull($duplicatedPicture2->pivot->caption_translation_key_id);
    }

    public function test_duplicates_empty_gallery()
    {
        // Create empty gallery
        $originalGallery = ContentGallery::factory()->create([
            'layout' => 'carousel',
            'columns' => 1,
        ]);

        // Duplicate the empty gallery
        $duplicatedGallery = $this->service->duplicateGalleryContent($originalGallery);

        // Assert gallery was duplicated
        $this->assertNotEquals($originalGallery->id, $duplicatedGallery->id);
        $this->assertEquals('carousel', $duplicatedGallery->layout);
        $this->assertEquals(1, $duplicatedGallery->columns);

        // Assert no pictures are attached
        $this->assertCount(0, $duplicatedGallery->pictures);
    }

    public function test_generates_unique_translation_key_with_multiple_iterations()
    {
        // Create the original key and multiple copy variations
        $originalKey = TranslationKey::factory()->create(['key' => 'test.key']);
        TranslationKey::factory()->create(['key' => 'test.key_copy']);
        TranslationKey::factory()->create(['key' => 'test.key_copy_1']);
        TranslationKey::factory()->create(['key' => 'test.key_copy_2']);

        // Create markdown with the original key
        $originalMarkdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $originalKey->id,
        ]);

        // Duplicate should create test.key_copy_3
        $duplicated = $this->service->duplicateMarkdownContent($originalMarkdown);
        $newKey = $duplicated->translationKey;

        $this->assertEquals('test.key_copy_3', $newKey->key);
    }
}
