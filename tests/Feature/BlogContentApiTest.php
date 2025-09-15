<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPostDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected BlogCategory $category;

    protected BlogPostDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = BlogCategory::factory()->create();
        $this->draft = BlogPostDraft::factory()->create([
            'category_id' => $this->category->id,
        ]);
    }

    public function test_can_create_markdown_content(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-content-markdown', [
                'content' => 'Test markdown content',
                'locale' => 'fr',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'translation_key_id',
            'translation_key' => [
                'translations' => [
                    '*' => ['locale', 'text'],
                ],
            ],
        ]);

        $this->assertDatabaseHas('blog_content_markdown', [
            'id' => $response->json('id'),
        ]);
    }

    public function test_can_create_blog_post_draft_content(): void
    {
        // First create a markdown content
        $markdownResponse = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-content-markdown', [
                'content' => 'Test content',
                'locale' => 'fr',
            ]);

        $markdownResponse->assertStatus(201);

        // Then create draft content
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-post-draft-contents', [
                'blog_post_draft_id' => $this->draft->id,
                'content_type' => 'App\\Models\\BlogContentMarkdown',
                'content_id' => $markdownResponse->json('id'),
                'order' => 1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'blog_post_draft_id',
            'content_type',
            'content_id',
            'order',
        ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $this->draft->id,
            'content_type' => 'App\\Models\\BlogContentMarkdown',
            'content_id' => $markdownResponse->json('id'),
        ]);
    }

    public function test_can_create_markdown_content_without_content(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-content-markdown', [
                'content' => null,
                'locale' => 'fr',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'translation_key_id',
            'translation_key' => [
                'translations' => [
                    '*' => ['locale', 'text'],
                ],
            ],
        ]);

        $this->assertDatabaseHas('blog_content_markdown', [
            'id' => $response->json('id'),
        ]);
    }

    public function test_markdown_content_validation_fails_without_locale(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-content-markdown', [
                'content' => 'Test content',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['locale']);
    }

    public function test_draft_content_validation_fails_without_draft_id(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/dashboard/api/blog-post-draft-contents', [
                'content_type' => 'App\\Models\\BlogContentMarkdown',
                'content_id' => 1,
                'order' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['blog_post_draft_id']);
    }
}
