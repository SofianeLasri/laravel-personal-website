<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\BlogPost;

use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Services\Conversion\BlogPost\BlogPostContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostContentSyncService::class)]
class BlogPostContentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogPostContentSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogPostContentSyncService::class);
    }

    #[Test]
    public function it_syncs_content_from_draft_to_blog_post(): void
    {
        $blogPost = BlogPost::factory()->create();
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);
        $markdown = ContentMarkdown::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->service->sync($draft, $blogPost);

        $this->assertEquals(1, $blogPost->contents()->count());
    }

    #[Test]
    public function it_deletes_existing_blog_post_content(): void
    {
        $blogPost = BlogPost::factory()->create();
        $oldMarkdown = ContentMarkdown::factory()->create();
        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown->id,
            'order' => 1,
        ]);

        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);
        $newMarkdown = ContentMarkdown::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $newMarkdown->id,
            'order' => 1,
        ]);

        $this->service->sync($draft, $blogPost);

        $blogPost->refresh();
        $this->assertEquals(1, $blogPost->contents()->count());
        // Old content should be deleted
        $this->assertNull(ContentMarkdown::find($oldMarkdown->id));
    }

    #[Test]
    public function it_syncs_multiple_contents(): void
    {
        $blogPost = BlogPost::factory()->create();
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);

        $markdown = ContentMarkdown::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $gallery = ContentGallery::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        $this->service->sync($draft, $blogPost);

        $this->assertEquals(2, $blogPost->contents()->count());
    }

    #[Test]
    public function it_preserves_content_order(): void
    {
        $blogPost = BlogPost::factory()->create();
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);

        $markdown1 = ContentMarkdown::factory()->create();
        $markdown2 = ContentMarkdown::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);

        $this->service->sync($draft, $blogPost);

        $blogPostContents = $blogPost->contents()->orderBy('order')->get();
        $this->assertEquals(1, $blogPostContents[0]->order);
        $this->assertEquals(2, $blogPostContents[1]->order);
    }

    #[Test]
    public function it_handles_empty_draft_content(): void
    {
        $blogPost = BlogPost::factory()->create();
        $oldMarkdown = ContentMarkdown::factory()->create();
        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown->id,
            'order' => 1,
        ]);

        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);
        // No draft content

        $this->service->sync($draft, $blogPost);

        $this->assertEquals(0, $blogPost->contents()->count());
    }

    #[Test]
    public function it_deletes_markdown_content_with_translation_key(): void
    {
        $blogPost = BlogPost::factory()->create();
        $markdown = ContentMarkdown::factory()->create();
        $translationKeyId = $markdown->translation_key_id;
        $blogPostContent = BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->service->deleteRecord($blogPostContent);

        $this->assertNull(ContentMarkdown::find($markdown->id));
        $this->assertDatabaseMissing('translation_keys', ['id' => $translationKeyId]);
    }

    #[Test]
    public function it_deletes_gallery_content_with_pictures(): void
    {
        $blogPost = BlogPost::factory()->create();
        $gallery = ContentGallery::factory()->withPictures(2)->create();
        $blogPostContent = BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $this->service->deleteRecord($blogPostContent);

        $this->assertNull(ContentGallery::find($gallery->id));
    }

    #[Test]
    public function it_deletes_video_content(): void
    {
        $blogPost = BlogPost::factory()->create();
        $contentVideo = ContentVideo::factory()->create();
        $blogPostContent = BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
            'order' => 1,
        ]);

        $this->service->deleteRecord($blogPostContent);

        $this->assertNull(ContentVideo::find($contentVideo->id));
    }
}
