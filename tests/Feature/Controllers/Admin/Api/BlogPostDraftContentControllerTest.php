<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\BlogPostDraftContentController;
use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogPostDraftContentController::class)]
class BlogPostDraftContentControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function store_creates_draft_content_with_automatic_order()
    {
        $draft = BlogPostDraft::factory()->create();
        $markdown = BlogContentMarkdown::factory()->create();

        // Create existing content with orders
        BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create(['order' => 1]);
        BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create(['order' => 2]);

        $response = $this->postJson(route('dashboard.api.blog-post-draft-contents.store'), [
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'blog_post_draft_id',
                'content_type',
                'content_id',
                'order',
                'content',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'blog_post_draft_id' => $draft->id,
                'content_type' => BlogContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 3, // Should be max order + 1
            ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function store_creates_draft_content_with_specified_order()
    {
        $draft = BlogPostDraft::factory()->create();
        $gallery = BlogContentGallery::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-draft-contents.store'), [
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 5,
        ]);

        $response->assertCreated()
            ->assertJson([
                'blog_post_draft_id' => $draft->id,
                'content_type' => BlogContentGallery::class,
                'content_id' => $gallery->id,
                'order' => 5,
            ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 5,
        ]);
    }

    #[Test]
    public function store_fails_with_invalid_data()
    {
        $response = $this->postJson(route('dashboard.api.blog-post-draft-contents.store'), [
            'content_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'blog_post_draft_id',
                    'content_id',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function store_fails_with_non_existent_blog_post_draft()
    {
        $markdown = BlogContentMarkdown::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-draft-contents.store'), [
            'blog_post_draft_id' => 99999,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'blog_post_draft_id',
                ],
            ]);
    }

    #[Test]
    public function store_loads_content_relationship()
    {
        $draft = BlogPostDraft::factory()->create();
        $video = BlogContentVideo::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-draft-contents.store'), [
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $video->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('content.id', $video->id)
            ->assertJsonStructure([
                'content' => [
                    'id',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function update_modifies_order_successfully()
    {
        $draftContent = BlogPostDraftContent::factory()->markdown()->create([
            'order' => 1,
        ]);

        $response = $this->putJson(
            route('dashboard.api.blog-post-draft-contents.update', $draftContent),
            [
                'order' => 5,
            ]
        );

        $response->assertOk()
            ->assertJson([
                'id' => $draftContent->id,
                'order' => 5,
            ])
            ->assertJsonStructure([
                'content',
            ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $draftContent->id,
            'order' => 5,
        ]);
    }

    #[Test]
    public function update_fails_with_invalid_order_type()
    {
        $draftContent = BlogPostDraftContent::factory()->create();

        $response = $this->putJson(
            route('dashboard.api.blog-post-draft-contents.update', $draftContent),
            [
                'order' => 'not_a_number',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'order',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function update_without_order_field_returns_unchanged()
    {
        $draftContent = BlogPostDraftContent::factory()->gallery()->create([
            'order' => 3,
        ]);

        $response = $this->putJson(
            route('dashboard.api.blog-post-draft-contents.update', $draftContent),
            []
        );

        $response->assertOk()
            ->assertJson([
                'id' => $draftContent->id,
                'order' => 3,
            ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $draftContent->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function update_loads_content_relationship()
    {
        $markdown = BlogContentMarkdown::factory()->create();
        $draftContent = BlogPostDraftContent::factory()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
        ]);

        $response = $this->putJson(
            route('dashboard.api.blog-post-draft-contents.update', $draftContent),
            [
                'order' => 10,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('content.id', $markdown->id)
            ->assertJsonStructure([
                'content' => [
                    'id',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function reorder_updates_content_order_successfully()
    {
        $draft = BlogPostDraft::factory()->create();

        $content1 = BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create(['order' => 1]);
        $content2 = BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create(['order' => 2]);
        $content3 = BlogPostDraftContent::factory()->forBlogPostDraft($draft)->create(['order' => 3]);

        $response = $this->postJson(
            route('dashboard.api.blog-post-draft-contents.reorder', $draft),
            [
                'content_ids' => [$content3->id, $content1->id, $content2->id],
            ]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Content reordered successfully',
            ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $content3->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $content1->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $content2->id,
            'order' => 3,
        ]);
    }

    #[Test]
    public function reorder_fails_with_missing_content_ids()
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.blog-post-draft-contents.reorder', $draft),
            []
        );

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'content_ids',
                ],
            ])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function reorder_fails_with_non_existent_content_ids()
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->postJson(
            route('dashboard.api.blog-post-draft-contents.reorder', $draft),
            [
                'content_ids' => [99999, 88888],
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'content_ids.0',
                'content_ids.1',
            ]);
    }

    #[Test]
    public function reorder_only_affects_specified_draft_contents()
    {
        $draft1 = BlogPostDraft::factory()->create();
        $draft2 = BlogPostDraft::factory()->create();

        $content1 = BlogPostDraftContent::factory()->forBlogPostDraft($draft1)->create(['order' => 1]);
        $content2 = BlogPostDraftContent::factory()->forBlogPostDraft($draft1)->create(['order' => 2]);
        $otherDraftContent = BlogPostDraftContent::factory()->forBlogPostDraft($draft2)->create(['order' => 1]);

        $response = $this->postJson(
            route('dashboard.api.blog-post-draft-contents.reorder', $draft1),
            [
                'content_ids' => [$content2->id, $content1->id],
            ]
        );

        $response->assertOk();

        // Verify draft1 contents are reordered
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $content2->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $content1->id,
            'order' => 2,
        ]);

        // Verify draft2 content is unchanged
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $otherDraftContent->id,
            'order' => 1,
        ]);
    }

    #[Test]
    public function destroy_deletes_draft_content_successfully()
    {
        $draftContent = BlogPostDraftContent::factory()->video()->create();
        $contentId = $draftContent->id;

        $response = $this->deleteJson(
            route('dashboard.api.blog-post-draft-contents.destroy', $draftContent)
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Content deleted successfully',
            ]);

        $this->assertDatabaseMissing('blog_post_draft_contents', [
            'id' => $contentId,
        ]);
    }

    #[Test]
    public function destroy_returns_404_for_non_existent_content()
    {
        $response = $this->deleteJson(
            route('dashboard.api.blog-post-draft-contents.destroy', 99999)
        );

        $response->assertNotFound();
    }
}
