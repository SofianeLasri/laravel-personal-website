<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\BlogContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogContentService::class)]
class BlogContentServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogContentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogContentService::class);
    }

    #[Test]
    public function it_creates_markdown_content_for_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $content = $this->service->createMarkdownContent($draft, $translationKey->id, 1);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentMarkdown::class, $content->content_type);
        $this->assertEquals(1, $content->order);
        $this->assertInstanceOf(BlogContentMarkdown::class, $content->content);
        $this->assertEquals($translationKey->id, $content->content->translation_key_id);
    }

    #[Test]
    public function it_creates_gallery_content_for_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $pictures = Picture::factory()->count(3)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $content = $this->service->createGalleryContent($draft, $galleryData, 2);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentGallery::class, $content->content_type);
        $this->assertEquals(2, $content->order);
        $this->assertInstanceOf(BlogContentGallery::class, $content->content);
        $this->assertEquals('grid', $content->content->layout);
        $this->assertEquals(3, $content->content->columns);
        $this->assertCount(3, $content->content->pictures);
    }

    #[Test]
    public function it_creates_video_content_for_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $content = $this->service->createVideoContent($draft, $video->id, 3, $captionKey->id);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentVideo::class, $content->content_type);
        $this->assertEquals(3, $content->order);
        $this->assertInstanceOf(BlogContentVideo::class, $content->content);
        $this->assertEquals($video->id, $content->content->video_id);
        $this->assertEquals($captionKey->id, $content->content->caption_translation_key_id);
    }

    #[Test]
    public function it_updates_markdown_content(): void
    {
        $markdown = BlogContentMarkdown::factory()->create();
        $newTranslationKey = TranslationKey::factory()->create();

        $updated = $this->service->updateMarkdownContent($markdown, $newTranslationKey->id);

        $this->assertEquals($newTranslationKey->id, $updated->translation_key_id);
    }

    #[Test]
    public function it_updates_gallery_content(): void
    {
        $gallery = BlogContentGallery::factory()->create(['layout' => 'grid', 'columns' => 2]);
        $oldPictures = Picture::factory()->count(2)->create();
        $gallery->pictures()->attach($oldPictures);

        $newPictures = Picture::factory()->count(3)->create();
        $updateData = [
            'layout' => 'carousel',
            'columns' => null,
            'pictures' => $newPictures->pluck('id')->toArray(),
        ];

        $updated = $this->service->updateGalleryContent($gallery, $updateData);

        $this->assertEquals('carousel', $updated->layout);
        $this->assertNull($updated->columns);
        $this->assertCount(3, $updated->pictures);
        $this->assertTrue($updated->pictures->contains($newPictures->first()));
    }

    #[Test]
    public function it_reorders_content_blocks(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $content1 = $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 1,
            'order' => 1,
        ]);

        $content2 = $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => 1,
            'order' => 2,
        ]);

        $content3 = $draft->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => 1,
            'order' => 3,
        ]);

        $newOrder = [$content3->id, $content1->id, $content2->id];

        $this->service->reorderContent($draft, $newOrder);

        $draft->refresh();
        $reorderedContents = $draft->contents()->orderBy('order')->get();

        $this->assertEquals($content3->id, $reorderedContents[0]->id);
        $this->assertEquals(1, $reorderedContents[0]->order);
        $this->assertEquals($content1->id, $reorderedContents[1]->id);
        $this->assertEquals(2, $reorderedContents[1]->order);
        $this->assertEquals($content2->id, $reorderedContents[2]->id);
        $this->assertEquals(3, $reorderedContents[2]->order);
    }

    #[Test]
    public function it_deletes_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        $content = $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $result = $this->service->deleteContent($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('blog_content_markdown', ['id' => $markdown->id]);
    }

    #[Test]
    public function it_duplicates_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $originalMarkdown = BlogContentMarkdown::factory()->create();

        $originalContent = $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $originalMarkdown->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicateContent($originalContent);

        $this->assertNotEquals($originalContent->id, $duplicate->id);
        $this->assertEquals($originalContent->content_type, $duplicate->content_type);
        $this->assertNotEquals($originalContent->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        $duplicatedMarkdown = $duplicate->content;
        $this->assertInstanceOf(BlogContentMarkdown::class, $duplicatedMarkdown);
        $this->assertEquals($originalMarkdown->translation_key_id, $duplicatedMarkdown->translation_key_id);
    }

    #[Test]
    public function it_validates_content_structure(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 1,
            'order' => 1,
        ]);

        $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => 1,
            'order' => 2,
        ]);

        $isValid = $this->service->validateContentStructure($draft);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_returns_false_for_empty_content_structure(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $isValid = $this->service->validateContentStructure($draft);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_updates_video_content(): void
    {
        $videoContent = BlogContentVideo::factory()->create();
        $newVideo = Video::factory()->create();
        $newCaptionKey = TranslationKey::factory()->create();

        $updated = $this->service->updateVideoContent($videoContent, $newVideo->id, $newCaptionKey->id);

        $this->assertEquals($newVideo->id, $updated->video_id);
        $this->assertEquals($newCaptionKey->id, $updated->caption_translation_key_id);
    }

    #[Test]
    public function it_updates_video_content_with_null_caption(): void
    {
        $videoContent = BlogContentVideo::factory()->create();
        $newVideo = Video::factory()->create();

        $updated = $this->service->updateVideoContent($videoContent, $newVideo->id, null);

        $this->assertEquals($newVideo->id, $updated->video_id);
        $this->assertNull($updated->caption_translation_key_id);
    }

    #[Test]
    public function it_creates_post_content(): void
    {
        $post = BlogPost::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        $content = $this->service->createPostContent($post, BlogContentMarkdown::class, $markdown->id, 1);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentMarkdown::class, $content->content_type);
        $this->assertEquals($markdown->id, $content->content_id);
        $this->assertEquals(1, $content->order);
        $this->assertEquals($post->id, $content->blog_post_id);
    }

    #[Test]
    public function it_reorders_content_blocks_for_published_post(): void
    {
        $post = BlogPost::factory()->create();

        $content1 = $post->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 1,
            'order' => 1,
        ]);

        $content2 = $post->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => 1,
            'order' => 2,
        ]);

        $content3 = $post->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => 1,
            'order' => 3,
        ]);

        $newOrder = [$content3->id, $content1->id, $content2->id];

        $this->service->reorderContent($post, $newOrder);

        $post->refresh();
        $reorderedContents = $post->contents()->orderBy('order')->get();

        $this->assertEquals($content3->id, $reorderedContents[0]->id);
        $this->assertEquals(1, $reorderedContents[0]->order);
        $this->assertEquals($content1->id, $reorderedContents[1]->id);
        $this->assertEquals(2, $reorderedContents[1]->order);
        $this->assertEquals($content2->id, $reorderedContents[2]->id);
        $this->assertEquals(3, $reorderedContents[2]->order);
    }

    #[Test]
    public function it_deletes_gallery_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = BlogContentGallery::factory()->create();
        $pictures = Picture::factory()->count(2)->create();
        $gallery->pictures()->attach($pictures->pluck('id')->toArray());

        $content = $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $result = $this->service->deleteContent($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('blog_content_galleries', ['id' => $gallery->id]);
        $this->assertEquals(0, $gallery->pictures()->count());
    }

    #[Test]
    public function it_deletes_video_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $videoContent = BlogContentVideo::factory()->create();

        $content = $draft->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $result = $this->service->deleteContent($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('blog_content_videos', ['id' => $videoContent->id]);
    }

    #[Test]
    public function it_deletes_published_post_content_block(): void
    {
        $post = BlogPost::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        $content = $post->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $result = $this->service->deleteContent($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('blog_content_markdown', ['id' => $markdown->id]);
    }

    #[Test]
    public function it_duplicates_gallery_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $originalGallery = BlogContentGallery::factory()->create(['layout' => 'grid', 'columns' => 3]);
        $pictures = Picture::factory()->count(2)->create();

        $pictureData = [];
        foreach ($pictures as $index => $picture) {
            $pictureData[$picture->id] = [
                'order' => $index + 1,
                'caption_translation_key_id' => TranslationKey::factory()->create()->id,
            ];
        }
        $originalGallery->pictures()->attach($pictureData);

        $originalContent = $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $originalGallery->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicateContent($originalContent);

        $this->assertNotEquals($originalContent->id, $duplicate->id);
        $this->assertEquals($originalContent->content_type, $duplicate->content_type);
        $this->assertNotEquals($originalContent->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        $duplicatedGallery = $duplicate->content;
        $this->assertInstanceOf(BlogContentGallery::class, $duplicatedGallery);
        $this->assertEquals($originalGallery->layout, $duplicatedGallery->layout);
        $this->assertEquals($originalGallery->columns, $duplicatedGallery->columns);
        $this->assertCount(2, $duplicatedGallery->pictures);

        foreach ($duplicatedGallery->pictures as $picture) {
            $this->assertContains($picture->id, $pictures->pluck('id')->toArray());
        }
    }

    #[Test]
    public function it_duplicates_video_content_block(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $originalVideo = BlogContentVideo::factory()->create();

        $originalContent = $draft->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => $originalVideo->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicateContent($originalContent);

        $this->assertNotEquals($originalContent->id, $duplicate->id);
        $this->assertEquals($originalContent->content_type, $duplicate->content_type);
        $this->assertNotEquals($originalContent->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        $duplicatedVideo = $duplicate->content;
        $this->assertInstanceOf(BlogContentVideo::class, $duplicatedVideo);
        $this->assertEquals($originalVideo->video_id, $duplicatedVideo->video_id);
        $this->assertEquals($originalVideo->caption_translation_key_id, $duplicatedVideo->caption_translation_key_id);
    }

    #[Test]
    public function it_duplicates_published_post_content_block(): void
    {
        $post = BlogPost::factory()->create();
        $originalMarkdown = BlogContentMarkdown::factory()->create();

        $originalContent = $post->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $originalMarkdown->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicateContent($originalContent);

        $this->assertNotEquals($originalContent->id, $duplicate->id);
        $this->assertEquals($originalContent->content_type, $duplicate->content_type);
        $this->assertNotEquals($originalContent->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        $duplicatedMarkdown = $duplicate->content;
        $this->assertInstanceOf(BlogContentMarkdown::class, $duplicatedMarkdown);
        $this->assertEquals($originalMarkdown->translation_key_id, $duplicatedMarkdown->translation_key_id);
    }

    #[Test]
    public function it_creates_gallery_content_with_empty_pictures_array(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => [],
        ];

        $content = $this->service->createGalleryContent($draft, $galleryData, 1);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentGallery::class, $content->content_type);
        $this->assertEquals(1, $content->order);
        $this->assertInstanceOf(BlogContentGallery::class, $content->content);
        $this->assertEquals('grid', $content->content->layout);
        $this->assertEquals(3, $content->content->columns);
        $this->assertCount(0, $content->content->pictures);
    }

    #[Test]
    public function it_creates_video_content_without_caption(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $video = Video::factory()->create();

        $content = $this->service->createVideoContent($draft, $video->id, 1);

        $this->assertNotNull($content);
        $this->assertEquals(BlogContentVideo::class, $content->content_type);
        $this->assertEquals(1, $content->order);
        $this->assertInstanceOf(BlogContentVideo::class, $content->content);
        $this->assertEquals($video->id, $content->content->video_id);
        $this->assertNull($content->content->caption_translation_key_id);
    }

    #[Test]
    public function it_updates_gallery_content_with_empty_pictures(): void
    {
        $gallery = BlogContentGallery::factory()->create(['layout' => 'grid', 'columns' => 2]);
        $oldPictures = Picture::factory()->count(2)->create();
        $gallery->pictures()->attach($oldPictures);

        $updateData = [
            'layout' => 'carousel',
            'columns' => null,
            'pictures' => [],
        ];

        $updated = $this->service->updateGalleryContent($gallery, $updateData);

        $this->assertEquals('carousel', $updated->layout);
        $this->assertNull($updated->columns);
        $this->assertCount(0, $updated->pictures);
    }

    #[Test]
    public function it_validates_content_structure_for_published_post(): void
    {
        $post = BlogPost::factory()->create();

        $post->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 1,
            'order' => 1,
        ]);

        $isValid = $this->service->validateContentStructure($post);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_returns_false_for_empty_published_post_content_structure(): void
    {
        $post = BlogPost::factory()->create();

        $isValid = $this->service->validateContentStructure($post);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_duplicates_gallery_content_without_pictures(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $originalGallery = BlogContentGallery::factory()->create(['layout' => 'grid', 'columns' => 3]);

        $originalContent = $draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $originalGallery->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicateContent($originalContent);

        $this->assertNotEquals($originalContent->id, $duplicate->id);
        $this->assertEquals($originalContent->content_type, $duplicate->content_type);
        $this->assertNotEquals($originalContent->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        $duplicatedGallery = $duplicate->content;
        $this->assertInstanceOf(BlogContentGallery::class, $duplicatedGallery);
        $this->assertEquals($originalGallery->layout, $duplicatedGallery->layout);
        $this->assertEquals($originalGallery->columns, $duplicatedGallery->columns);
        $this->assertCount(0, $duplicatedGallery->pictures);
    }
}
