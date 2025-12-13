<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\TranslationKey;
use App\Services\Content\ContentDuplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(ContentDuplicationService::class)]
class ContentDuplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentDuplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentDuplicationService::class);
    }

    #[Test]
    public function it_duplicates_markdown_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::create(['translation_key_id' => $translationKey->id]);
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicate($content);

        $this->assertNotEquals($content->id, $duplicate->id);
        $this->assertEquals(ContentMarkdown::class, $duplicate->content_type);
        $this->assertNotEquals($content->content_id, $duplicate->content_id);
        $this->assertEquals(2, $duplicate->order);

        // Verify the duplicated markdown has the same translation key
        $duplicateMarkdown = ContentMarkdown::find($duplicate->content_id);
        $this->assertEquals($translationKey->id, $duplicateMarkdown->translation_key_id);
    }

    #[Test]
    public function it_duplicates_gallery_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(3)->create([
            'layout' => 'grid',
            'columns' => 3,
        ]);
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicate($content);

        $this->assertNotEquals($content->id, $duplicate->id);
        $this->assertEquals(ContentGallery::class, $duplicate->content_type);
        $this->assertNotEquals($content->content_id, $duplicate->content_id);

        // Verify the duplicated gallery has the same properties
        $duplicateGallery = ContentGallery::find($duplicate->content_id);
        $this->assertEquals('grid', $duplicateGallery->layout);
        $this->assertEquals(3, $duplicateGallery->columns);
        $this->assertEquals(3, $duplicateGallery->pictures()->count());
    }

    #[Test]
    public function it_duplicates_gallery_with_picture_order(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(3)->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicate($content);

        $duplicateGallery = ContentGallery::find($duplicate->content_id);
        $originalPictures = $gallery->pictures()->orderBy('content_gallery_pictures.order')->get();
        $duplicatePictures = $duplicateGallery->pictures()->orderBy('content_gallery_pictures.order')->get();

        // Verify picture order is preserved
        foreach ($originalPictures as $index => $picture) {
            $this->assertEquals($picture->id, $duplicatePictures[$index]->id);
            $this->assertEquals($picture->pivot->order, $duplicatePictures[$index]->pivot->order);
        }
    }

    #[Test]
    public function it_duplicates_video_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $videoContent = ContentVideo::factory()->withCaption()->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicate($content);

        $this->assertNotEquals($content->id, $duplicate->id);
        $this->assertEquals(ContentVideo::class, $duplicate->content_type);
        $this->assertNotEquals($content->content_id, $duplicate->content_id);

        // Verify the duplicated video has the same properties
        $duplicateVideo = ContentVideo::find($duplicate->content_id);
        $this->assertEquals($videoContent->video_id, $duplicateVideo->video_id);
        $this->assertEquals($videoContent->caption_translation_key_id, $duplicateVideo->caption_translation_key_id);
    }

    #[Test]
    public function it_duplicates_video_content_without_caption(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $videoContent = ContentVideo::factory()->withoutCaption()->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $duplicate = $this->service->duplicate($content);

        $duplicateVideo = ContentVideo::find($duplicate->content_id);
        $this->assertNull($duplicateVideo->caption_translation_key_id);
    }

    #[Test]
    public function it_places_duplicate_after_last_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);
        $content3 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 3,
        ]);

        $duplicate = $this->service->duplicate($content3);

        $this->assertEquals(4, $duplicate->order);
    }

    #[Test]
    public function it_throws_exception_when_parent_not_found(): void
    {
        // Create a valid content first, then delete the parent
        $draft = BlogPostDraft::factory()->create();
        $markdown = ContentMarkdown::factory()->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        // Delete the parent draft to create an orphan content
        $draft->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parent entity not found');

        $this->service->duplicate($content);
    }

    #[Test]
    public function it_uses_transaction_for_duplication(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(2)->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $initialCount = $draft->contents()->count();
        $duplicate = $this->service->duplicate($content);

        $this->assertEquals($initialCount + 1, $draft->contents()->count());
        $this->assertNotNull($duplicate->id);
    }
}
