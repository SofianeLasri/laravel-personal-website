<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\BlogPost;

use App\Enums\BlogPostType;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentMarkdown;
use App\Services\Conversion\BlogPost\DraftToBlogPostConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DraftToBlogPostConverter::class)]
class DraftToBlogPostConverterTest extends TestCase
{
    use RefreshDatabase;

    private DraftToBlogPostConverter $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DraftToBlogPostConverter::class);
        Queue::fake();
    }

    #[Test]
    public function it_converts_new_draft_to_blog_post(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();

        $blogPost = $this->service->convert($draft);

        $this->assertInstanceOf(BlogPost::class, $blogPost);
        $this->assertEquals($draft->slug, $blogPost->slug);
        $this->assertEquals($draft->title_translation_key_id, $blogPost->title_translation_key_id);
        $this->assertEquals($draft->type, $blogPost->type);
        $this->assertEquals($draft->category_id, $blogPost->category_id);
    }

    #[Test]
    public function it_links_draft_to_created_blog_post(): void
    {
        $draft = BlogPostDraft::factory()->article()->create([
            'original_blog_post_id' => null,
        ]);

        $blogPost = $this->service->convert($draft);

        $draft->refresh();
        $this->assertEquals($blogPost->id, $draft->original_blog_post_id);
    }

    #[Test]
    public function it_updates_existing_blog_post(): void
    {
        $blogPost = BlogPost::factory()->create([
            'slug' => 'original-slug',
        ]);
        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $blogPost->id,
            'slug' => 'updated-slug',
            'type' => BlogPostType::ARTICLE,
            'category_id' => $blogPost->category_id,
        ]);

        $updatedBlogPost = $this->service->convert($draft);

        $this->assertEquals($blogPost->id, $updatedBlogPost->id);
        $this->assertEquals('updated-slug', $updatedBlogPost->slug);
    }

    #[Test]
    public function it_converts_draft_with_content(): void
    {
        $draft = BlogPostDraft::factory()->article()->create();
        $markdown = ContentMarkdown::factory()->create();
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $blogPost = $this->service->convert($draft);

        $this->assertEquals(1, $blogPost->contents()->count());
    }

    #[Test]
    public function it_preserves_draft_type(): void
    {
        $draft = BlogPostDraft::factory()->gameReview()->create();

        $blogPost = $this->service->convert($draft);

        $this->assertEquals(BlogPostType::GAME_REVIEW, $blogPost->type);
    }

    #[Test]
    public function it_preserves_cover_picture(): void
    {
        $draft = BlogPostDraft::factory()->create();

        $blogPost = $this->service->convert($draft);

        $this->assertEquals($draft->cover_picture_id, $blogPost->cover_picture_id);
    }
}
