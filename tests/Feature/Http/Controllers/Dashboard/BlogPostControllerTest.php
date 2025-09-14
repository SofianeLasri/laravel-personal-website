<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Dashboard;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_lists_drafts_and_posts(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPostDraft::factory()->count(2)->create(['category_id' => $category->id]);
        BlogPost::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->getJson('/dashboard/api/blog/posts');

        $response->assertOk();
        $response->assertJsonStructure([
            'drafts' => [
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'type',
                        'status',
                        'category',
                        'cover_picture',
                        'blog_post_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
            'posts' => [
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'type',
                        'status',
                        'category',
                        'cover_picture',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPostDraft::factory()->count(2)->create(['category_id' => $category->id]);
        BlogPost::factory()->count(3)->create(['category_id' => $category->id]);

        $response = $this->getJson('/dashboard/api/blog/posts?status=drafts');

        $response->assertOk();
        $this->assertCount(2, $response->json('drafts.data'));
        $this->assertEmpty($response->json('posts'));
    }

    #[Test]
    public function it_filters_by_type(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPostDraft::factory()->count(2)->create([
            'category_id' => $category->id,
            'type' => 'article',
        ]);
        BlogPostDraft::factory()->count(1)->create([
            'category_id' => $category->id,
            'type' => 'game_review',
        ]);

        $response = $this->getJson('/dashboard/api/blog/posts?status=drafts&type=article');

        $response->assertOk();
        $this->assertCount(2, $response->json('drafts.data'));
    }

    #[Test]
    public function it_filters_by_category(): void
    {
        $category1 = BlogCategory::factory()->create();
        $category2 = BlogCategory::factory()->create();
        BlogPostDraft::factory()->count(2)->create(['category_id' => $category1->id]);
        BlogPostDraft::factory()->count(1)->create(['category_id' => $category2->id]);

        $response = $this->getJson("/dashboard/api/blog/posts?status=drafts&category_id={$category1->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('drafts.data'));
    }

    #[Test]
    public function it_shows_a_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson("/dashboard/api/blog/drafts/{$draft->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $draft->id,
            'slug' => $draft->slug,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function it_shows_a_post(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->getJson("/dashboard/api/blog/posts/{$post->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $post->id,
            'slug' => $post->slug,
            'status' => $post->status,
        ]);
    }

    #[Test]
    public function it_creates_a_new_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $picture = Picture::factory()->create();

        $data = [
            'slug' => 'test-article',
            'type' => 'article',
            'category_id' => $category->id,
            'cover_picture_id' => $picture->id,
        ];

        $response = $this->postJson('/dashboard/api/blog/drafts', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => 'test-article',
            'type' => 'article',
            'category_id' => $category->id,
            'cover_picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating_draft(): void
    {
        $response = $this->postJson('/dashboard/api/blog/drafts', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug', 'type', 'category_id']);
    }

    #[Test]
    public function it_validates_unique_slug_when_creating_draft(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPostDraft::factory()->create([
            'slug' => 'existing-slug',
            'category_id' => $category->id,
        ]);

        $response = $this->postJson('/dashboard/api/blog/drafts', [
            'slug' => 'existing-slug',
            'type' => 'article',
            'category_id' => $category->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function it_validates_type_when_creating_draft(): void
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson('/dashboard/api/blog/drafts', [
            'slug' => 'test-slug',
            'type' => 'invalid_type',
            'category_id' => $category->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function it_updates_a_draft(): void
    {
        $category1 = BlogCategory::factory()->create();
        $category2 = BlogCategory::factory()->create();
        $picture = Picture::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'slug' => 'old-slug',
            'type' => 'article',
            'category_id' => $category1->id,
        ]);

        $response = $this->putJson("/dashboard/api/blog/drafts/{$draft->id}", [
            'slug' => 'new-slug',
            'type' => 'game_review',
            'category_id' => $category2->id,
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('blog_post_drafts', [
            'id' => $draft->id,
            'slug' => 'new-slug',
            'type' => 'game_review',
            'category_id' => $category2->id,
            'cover_picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function it_deletes_a_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/dashboard/api/blog/drafts/{$draft->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('blog_post_drafts', ['id' => $draft->id]);
    }

    #[Test]
    public function it_deletes_a_post(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/dashboard/api/blog/posts/{$post->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    #[Test]
    public function it_publishes_a_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/dashboard/api/blog/drafts/{$draft->id}/publish");

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'slug',
            'type',
            'status',
            'category_id',
            'cover_picture_id',
            'published_at',
            'created_at',
            'updated_at',
        ]);

        // Check that a blog post was created
        $this->assertDatabaseHas('blog_posts', [
            'slug' => $draft->slug,
            'type' => $draft->type,
            'category_id' => $draft->category_id,
            'status' => 'published',
        ]);
    }

    #[Test]
    public function it_creates_draft_from_existing_post(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->postJson("/dashboard/api/blog/posts/{$post->id}/create-draft", [
            'copy_content' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => $post->slug,
            'type' => $post->type,
            'category_id' => $post->category_id,
            'blog_post_id' => $post->id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function it_creates_draft_from_existing_post_with_content_copy(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->withContent()->create(['category_id' => $category->id]);

        $response = $this->postJson("/dashboard/api/blog/posts/{$post->id}/create-draft", [
            'copy_content' => true,
        ]);

        $response->assertCreated();

        $draft = BlogPostDraft::where('blog_post_id', $post->id)->first();
        $this->assertNotNull($draft);
        $this->assertTrue($draft->contents()->exists());
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/dashboard/api/blog/posts');

        $response->assertUnauthorized();
    }

    #[Test]
    public function draft_not_found_returns_404(): void
    {
        $response = $this->getJson('/dashboard/api/blog/drafts/999');

        $response->assertNotFound();
    }

    #[Test]
    public function post_not_found_returns_404(): void
    {
        $response = $this->getJson('/dashboard/api/blog/posts/999');

        $response->assertNotFound();
    }
}
