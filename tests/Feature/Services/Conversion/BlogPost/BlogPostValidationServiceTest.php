<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\BlogPost;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentVideo;
use App\Models\Video;
use App\Services\Conversion\BlogPost\BlogPostValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostValidationService::class)]
class BlogPostValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogPostValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogPostValidationService::class);
    }

    #[Test]
    public function it_validates_valid_draft(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();

        // Should not throw
        $this->service->validate($draft);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_for_missing_title_translation_key(): void
    {
        // Create valid draft first, then modify the attribute
        $draft = BlogPostDraft::factory()->article()->create();
        $draft->title_translation_key_id = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_missing_slug(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $draft->slug = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_missing_type(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $draft->type = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_missing_category(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $draft->category_id = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_validates_draft_with_ready_video(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
        ]);
        $contentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
        ]);

        // Should not throw
        $this->service->validate($draft);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_for_video_without_cover(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
            'cover_picture_id' => null,
        ]);
        $contentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_video_still_transcoding(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $video = Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
        ]);
        $contentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_auto_publishes_private_video(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PRIVATE,
        ]);
        $contentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
        ]);

        $this->service->validateVideos($draft);

        $video->refresh();
        $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
    }

    #[Test]
    public function it_validates_draft_without_video_content(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();

        // Should not throw
        $this->service->validateVideos($draft);

        $this->assertTrue(true);
    }
}
