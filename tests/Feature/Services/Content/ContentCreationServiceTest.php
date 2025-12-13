<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\BlogPostDraft;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\Content\ContentCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentCreationService::class)]
class ContentCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentCreationService::class);
    }

    #[Test]
    public function it_creates_markdown_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $content = $this->service->createMarkdown($draft, $translationKey->id, 1);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('content_markdowns', [
            'translation_key_id' => $translationKey->id,
        ]);
    }

    #[Test]
    public function it_creates_markdown_content_with_correct_order(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $translationKey1 = TranslationKey::factory()->create();
        $translationKey2 = TranslationKey::factory()->create();

        $this->service->createMarkdown($draft, $translationKey1->id, 1);
        $this->service->createMarkdown($draft, $translationKey2->id, 2);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->assertEquals(2, $draft->contents()->count());
    }

    #[Test]
    public function it_creates_gallery_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => [],
        ];

        $content = $this->service->createGallery($draft, $galleryData, 1);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('content_galleries', [
            'layout' => 'grid',
            'columns' => 3,
        ]);
    }

    #[Test]
    public function it_creates_gallery_content_with_pictures(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $pictures = Picture::factory()->count(3)->create();
        $galleryData = [
            'layout' => 'masonry',
            'columns' => 2,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $content = $this->service->createGallery($draft, $galleryData, 1);

        $gallery = ContentGallery::where('layout', 'masonry')->first();
        $this->assertNotNull($gallery);
        $this->assertEquals(3, $gallery->pictures()->count());

        // Verify picture ordering
        $orderedPictures = $gallery->pictures()->orderBy('content_gallery_pictures.order')->get();
        foreach ($orderedPictures as $index => $picture) {
            $this->assertEquals($index + 1, $picture->pivot->order);
        }
    }

    #[Test]
    public function it_creates_gallery_content_without_columns(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $galleryData = [
            'layout' => 'slider',
            'pictures' => [],
        ];

        $content = $this->service->createGallery($draft, $galleryData, 1);

        $this->assertDatabaseHas('content_galleries', [
            'layout' => 'slider',
            'columns' => null,
        ]);
    }

    #[Test]
    public function it_creates_video_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $video = Video::factory()->create();

        $content = $this->service->createVideo($draft, $video->id, 1);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('content_videos', [
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);
    }

    #[Test]
    public function it_creates_video_content_with_caption(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $content = $this->service->createVideo($draft, $video->id, 1, $captionKey->id);

        $this->assertDatabaseHas('content_videos', [
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);
    }

    #[Test]
    public function it_returns_content_model_after_creation(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $content = $this->service->createMarkdown($draft, $translationKey->id, 1);

        $this->assertNotNull($content->id);
        $this->assertEquals(ContentMarkdown::class, $content->content_type);
        $this->assertEquals(1, $content->order);
    }
}
