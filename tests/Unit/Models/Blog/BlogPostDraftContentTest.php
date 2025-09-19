<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Blog;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostDraftContent::class)]
class BlogPostDraftContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_blog_post_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->create(['blog_post_draft_id' => $draft->id]);

        $this->assertInstanceOf(BlogPostDraft::class, $content->blogPostDraft);
        $this->assertEquals($draft->id, $content->blogPostDraft->id);
    }

    #[Test]
    public function it_has_a_polymorphic_content_relationship(): void
    {
        $markdown = BlogContentMarkdown::factory()->create();
        $content = BlogPostDraftContent::factory()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $this->assertInstanceOf(BlogContentMarkdown::class, $content->content);
        $this->assertEquals($markdown->id, $content->content->id);
    }

    #[Test]
    public function it_can_have_markdown_content(): void
    {
        $content = BlogPostDraftContent::factory()->markdown()->create();

        $this->assertEquals(BlogContentMarkdown::class, $content->content_type);
        $this->assertInstanceOf(BlogContentMarkdown::class, $content->content);
    }

    #[Test]
    public function it_can_have_gallery_content(): void
    {
        $content = BlogPostDraftContent::factory()->gallery()->create();

        $this->assertEquals(BlogContentGallery::class, $content->content_type);
        $this->assertInstanceOf(BlogContentGallery::class, $content->content);
    }

    #[Test]
    public function it_can_have_video_content(): void
    {
        $content = BlogPostDraftContent::factory()->video()->create();

        $this->assertEquals(BlogContentVideo::class, $content->content_type);
        $this->assertInstanceOf(BlogContentVideo::class, $content->content);
    }

    #[Test]
    public function it_has_fillable_fields(): void
    {
        $expectedFillable = [
            'blog_post_draft_id',
            'content_type',
            'content_id',
            'order',
        ];

        $content = new BlogPostDraftContent;

        $this->assertEquals($expectedFillable, $content->getFillable());
    }

    #[Test]
    public function it_casts_fields_correctly(): void
    {
        $content = BlogPostDraftContent::factory()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 123,
            'order' => 5,
        ]);

        $this->assertIsString($content->content_type);
        $this->assertIsInt($content->content_id);
        $this->assertIsInt($content->order);
        $this->assertEquals(123, $content->content_id);
        $this->assertEquals(5, $content->order);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $content = BlogPostDraftContent::factory()->create();

        $this->assertNotNull($content->created_at);
        $this->assertNotNull($content->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $content->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $content->updated_at);
    }

    #[Test]
    public function it_stores_order_correctly(): void
    {
        $content1 = BlogPostDraftContent::factory()->create(['order' => 1]);
        $content2 = BlogPostDraftContent::factory()->create(['order' => 5]);
        $content3 = BlogPostDraftContent::factory()->create(['order' => 10]);

        $this->assertEquals(1, $content1->order);
        $this->assertEquals(5, $content2->order);
        $this->assertEquals(10, $content3->order);
    }

    #[Test]
    public function it_can_be_created_with_factory(): void
    {
        $content = BlogPostDraftContent::factory()->create();

        $this->assertInstanceOf(BlogPostDraftContent::class, $content);
        $this->assertNotNull($content->blog_post_draft_id);
        $this->assertNotNull($content->content_type);
        $this->assertNotNull($content->content_id);
        $this->assertNotNull($content->order);
    }

    #[Test]
    public function it_can_be_created_for_specific_blog_post_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create();

        $this->assertEquals($draft->id, $content->blog_post_draft_id);
        $this->assertEquals($draft->id, $content->blogPostDraft->id);
    }

    #[Test]
    public function it_creates_content_with_random_type_from_factory(): void
    {
        $content = BlogPostDraftContent::factory()->create();

        $this->assertContains($content->content_type, [
            BlogContentMarkdown::class,
            BlogContentGallery::class,
            BlogContentVideo::class,
        ]);
    }

    #[Test]
    public function it_persists_all_fields_to_database(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        $content = BlogPostDraftContent::create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 3,
        ]);

        $retrieved = BlogPostDraftContent::find($content->id);

        $this->assertEquals($draft->id, $retrieved->blog_post_draft_id);
        $this->assertEquals(BlogContentMarkdown::class, $retrieved->content_type);
        $this->assertEquals($markdown->id, $retrieved->content_id);
        $this->assertEquals(3, $retrieved->order);
    }

    #[Test]
    public function it_loads_polymorphic_content_correctly(): void
    {
        $gallery = BlogContentGallery::factory()->create();
        $content = BlogPostDraftContent::factory()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
        ]);

        $retrieved = BlogPostDraftContent::with('content')->find($content->id);

        $this->assertNotNull($retrieved->content);
        $this->assertEquals($gallery->id, $retrieved->content->id);
        $this->assertInstanceOf(BlogContentGallery::class, $retrieved->content);
    }

    #[Test]
    public function it_loads_blog_post_draft_relationship_correctly(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $content = BlogPostDraftContent::factory()->create(['blog_post_draft_id' => $draft->id]);

        $retrieved = BlogPostDraftContent::with('blogPostDraft')->find($content->id);

        $this->assertNotNull($retrieved->blogPostDraft);
        $this->assertEquals($draft->id, $retrieved->blogPostDraft->id);
    }

    #[Test]
    public function it_can_update_order_field(): void
    {
        $content = BlogPostDraftContent::factory()->create(['order' => 1]);

        $content->update(['order' => 5]);

        $this->assertEquals(5, $content->fresh()->order);
    }

    #[Test]
    public function it_can_update_content_relationship(): void
    {
        $markdown1 = BlogContentMarkdown::factory()->create();
        $markdown2 = BlogContentMarkdown::factory()->create();

        $content = BlogPostDraftContent::factory()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown1->id,
        ]);

        $content->update(['content_id' => $markdown2->id]);

        $this->assertEquals($markdown2->id, $content->fresh()->content_id);
        $this->assertEquals($markdown2->id, $content->fresh()->content->id);
    }

    #[Test]
    public function it_handles_multiple_contents_for_same_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $content1 = BlogPostDraftContent::factory()->markdown()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 1,
        ]);

        $content2 = BlogPostDraftContent::factory()->gallery()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $content3 = BlogPostDraftContent::factory()->video()->create([
            'blog_post_draft_id' => $draft->id,
            'order' => 3,
        ]);

        $this->assertEquals($draft->id, $content1->blog_post_draft_id);
        $this->assertEquals($draft->id, $content2->blog_post_draft_id);
        $this->assertEquals($draft->id, $content3->blog_post_draft_id);

        $this->assertEquals(1, $content1->order);
        $this->assertEquals(2, $content2->order);
        $this->assertEquals(3, $content3->order);

        $this->assertInstanceOf(BlogContentMarkdown::class, $content1->content);
        $this->assertInstanceOf(BlogContentGallery::class, $content2->content);
        $this->assertInstanceOf(BlogContentVideo::class, $content3->content);
    }
}
