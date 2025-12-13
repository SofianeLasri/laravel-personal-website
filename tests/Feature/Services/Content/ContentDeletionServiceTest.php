<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Services\Content\ContentDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentDeletionService::class)]
class ContentDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentDeletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentDeletionService::class);
    }

    #[Test]
    public function it_deletes_markdown_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $markdown = ContentMarkdown::factory()->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $result = $this->service->delete($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('content_markdowns', ['id' => $markdown->id]);
    }

    #[Test]
    public function it_deletes_video_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $videoContent = ContentVideo::factory()->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
        ]);

        $result = $this->service->delete($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('content_videos', ['id' => $videoContent->id]);
    }

    #[Test]
    public function it_deletes_gallery_content_and_detaches_pictures(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(3)->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
        ]);

        // Verify pictures are attached
        $this->assertEquals(3, $gallery->pictures()->count());

        $result = $this->service->delete($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('content_galleries', ['id' => $gallery->id]);
        $this->assertDatabaseMissing('content_gallery_pictures', [
            'content_gallery_id' => $gallery->id,
        ]);
    }

    #[Test]
    public function it_deletes_all_content_for_parent(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->count(3)->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $count = $this->service->deleteAll($draft);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $draft->contents()->count());
    }

    #[Test]
    public function it_returns_zero_when_no_content_to_delete(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $count = $this->service->deleteAll($draft);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_handles_content_without_actual_content(): void
    {
        $draft = BlogPostDraft::factory()->create();
        // Create content with a non-existent content_id
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => 99999,
        ]);

        $result = $this->service->delete($content);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
    }

    #[Test]
    public function it_deletes_mixed_content_types(): void
    {
        $draft = BlogPostDraft::factory()->create();
        BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
        ]);
        BlogPostDraftContent::factory()->video()->create([
            'blog_post_draft_id' => $draft->id,
        ]);
        BlogPostDraftContent::factory()->gallery()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $count = $this->service->deleteAll($draft);

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $draft->contents()->count());
    }

    #[Test]
    public function it_uses_transaction_for_deletion(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(2)->create();
        $content = BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
        ]);

        // The deletion should be atomic
        $result = $this->service->delete($content);

        $this->assertTrue($result);
        // All related records should be deleted
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('content_galleries', ['id' => $gallery->id]);
    }
}
