<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\BlogCategory;
use App\Models\BlogContentMarkdown;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\Picture;
use App\Services\BlogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogService::class);
    }

    #[Test]
    public function it_creates_a_blog_post_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $picture = Picture::factory()->create();

        $data = [
            'slug' => 'test-blog-post',
            'type' => 'standard',
            'category_id' => $category->id,
            'cover_picture_id' => $picture->id,
        ];

        $draft = $this->service->createDraft($data);

        $this->assertInstanceOf(BlogPostDraft::class, $draft);
        $this->assertEquals('test-blog-post', $draft->slug);
        $this->assertEquals('standard', $draft->type);
        $this->assertEquals($category->id, $draft->category_id);
        $this->assertEquals($picture->id, $draft->cover_picture_id);
        $this->assertNull($draft->blog_post_id);
    }

    #[Test]
    public function it_creates_a_draft_from_existing_post(): void
    {
        $post = BlogPost::factory()->published()->create();

        $draft = $this->service->createDraftFromPost($post);

        $this->assertInstanceOf(BlogPostDraft::class, $draft);
        $this->assertEquals($post->id, $draft->blog_post_id);
        $this->assertEquals($post->slug, $draft->slug);
        $this->assertEquals($post->type, $draft->type);
        $this->assertEquals($post->category_id, $draft->category_id);
        $this->assertEquals($post->cover_picture_id, $draft->cover_picture_id);
    }

    #[Test]
    public function it_copies_content_when_creating_draft_from_post(): void
    {
        $post = BlogPost::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft = $this->service->createDraftFromPost($post, true);

        $this->assertCount(1, $draft->contents);
        $this->assertEquals(BlogContentMarkdown::class, $draft->contents->first()->content_type);
        $this->assertEquals($markdown->id, $draft->contents->first()->content_id);
    }

    #[Test]
    public function it_publishes_a_draft_to_new_post(): void
    {
        $draft = BlogPostDraft::factory()->create([
            'blog_post_id' => null,
            'status' => 'draft',
        ]);

        $post = $this->service->publishDraft($draft);

        $this->assertInstanceOf(BlogPost::class, $post);
        $this->assertEquals($draft->slug, $post->slug);
        $this->assertEquals($draft->type, $post->type);
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertEquals($draft->category_id, $post->category_id);
        $this->assertEquals($draft->cover_picture_id, $post->cover_picture_id);
    }

    #[Test]
    public function it_publishes_a_draft_to_existing_post(): void
    {
        $post = BlogPost::factory()->create([
            'slug' => 'old-slug',
            'type' => 'standard',
        ]);

        $draft = BlogPostDraft::factory()->create([
            'blog_post_id' => $post->id,
            'slug' => 'new-slug',
            'type' => 'game_review',
        ]);

        $updatedPost = $this->service->publishDraft($draft);

        $this->assertEquals($post->id, $updatedPost->id);
        $this->assertEquals('new-slug', $updatedPost->slug);
        $this->assertEquals('game_review', $updatedPost->type);
        $this->assertEquals('published', $updatedPost->status);
    }

    #[Test]
    public function it_copies_content_when_publishing(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $post = $this->service->publishDraft($draft);

        $this->assertCount(1, $post->contents);
        $this->assertEquals(BlogContentMarkdown::class, $post->contents->first()->content_type);
        $this->assertEquals($markdown->id, $post->contents->first()->content_id);
        $this->assertEquals(1, $post->contents->first()->order);
    }

    #[Test]
    public function it_handles_game_review_when_publishing(): void
    {
        $draft = BlogPostDraft::factory()->create(['type' => 'game_review']);
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $post = $this->service->publishDraft($draft);

        $this->assertNotNull($post->gameReview);
        $this->assertEquals($gameReviewDraft->game_title, $post->gameReview->game_title);
        $this->assertEquals($gameReviewDraft->developer, $post->gameReview->developer);
        $this->assertEquals($gameReviewDraft->publisher, $post->gameReview->publisher);
    }

    #[Test]
    public function it_deletes_a_draft(): void
    {
        $draft = BlogPostDraft::factory()->create();
        $draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => 1,
            'order' => 1,
        ]);

        $result = $this->service->deleteDraft($draft);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_post_drafts', ['id' => $draft->id]);
        $this->assertDatabaseMissing('blog_post_draft_contents', ['blog_post_draft_id' => $draft->id]);
    }

    #[Test]
    public function it_deletes_a_post_and_its_draft(): void
    {
        $post = BlogPost::factory()->create();
        $draft = BlogPostDraft::factory()->create(['blog_post_id' => $post->id]);

        $result = $this->service->deletePost($post);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('blog_post_drafts', ['blog_post_id' => $post->id]);
    }

    #[Test]
    public function it_updates_draft_data(): void
    {
        $draft = BlogPostDraft::factory()->create([
            'slug' => 'old-slug',
            'type' => 'standard',
        ]);

        $newCategory = BlogCategory::factory()->create();
        $data = [
            'slug' => 'new-slug',
            'type' => 'game_review',
            'category_id' => $newCategory->id,
        ];

        $updatedDraft = $this->service->updateDraft($draft, $data);

        $this->assertEquals('new-slug', $updatedDraft->slug);
        $this->assertEquals('game_review', $updatedDraft->type);
        $this->assertEquals($newCategory->id, $updatedDraft->category_id);
    }

    #[Test]
    public function it_gets_paginated_published_posts(): void
    {
        BlogPost::factory()->count(3)->create(['status' => 'draft']);
        BlogPost::factory()->count(15)->create([
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $result = $this->service->getPaginatedPosts(10);

        $this->assertEquals(15, $result->total());
        $this->assertCount(10, $result->items());
        $this->assertEquals('published', $result->first()->status);
    }

    #[Test]
    public function it_filters_posts_by_category(): void
    {
        $category1 = BlogCategory::factory()->create();
        $category2 = BlogCategory::factory()->create();

        BlogPost::factory()->count(3)->published()->create(['category_id' => $category1->id]);
        BlogPost::factory()->count(2)->published()->create(['category_id' => $category2->id]);

        $result = $this->service->getPaginatedPosts(10, $category1->id);

        $this->assertEquals(3, $result->total());
        $this->assertTrue($result->every(fn ($post) => $post->category_id === $category1->id));
    }

    #[Test]
    public function it_filters_posts_by_type(): void
    {
        BlogPost::factory()->count(3)->published()->create(['type' => 'standard']);
        BlogPost::factory()->count(2)->published()->create(['type' => 'game_review']);

        $result = $this->service->getPaginatedPosts(10, null, 'game_review');

        $this->assertEquals(2, $result->total());
        $this->assertTrue($result->every(fn ($post) => $post->type === 'game_review'));
    }

    #[Test]
    public function it_finds_post_by_slug_with_relations(): void
    {
        $post = BlogPost::factory()->published()->create(['slug' => 'test-slug']);
        BlogPostContent::factory()->count(2)->create(['blog_post_id' => $post->id]);

        if ($post->type === 'game_review') {
            GameReview::factory()->create(['blog_post_id' => $post->id]);
        }

        $foundPost = $this->service->findBySlug('test-slug');

        $this->assertNotNull($foundPost);
        $this->assertEquals('test-slug', $foundPost->slug);
        $this->assertTrue($foundPost->relationLoaded('category'));
        $this->assertTrue($foundPost->relationLoaded('coverPicture'));
        $this->assertTrue($foundPost->relationLoaded('contents'));
    }

    #[Test]
    public function it_returns_null_for_non_existent_slug(): void
    {
        $post = $this->service->findBySlug('non-existent');

        $this->assertNull($post);
    }

    #[Test]
    public function it_returns_null_for_unpublished_post_slug(): void
    {
        BlogPost::factory()->create([
            'slug' => 'draft-post',
            'status' => 'draft',
        ]);

        $post = $this->service->findBySlug('draft-post');

        $this->assertNull($post);
    }
}
