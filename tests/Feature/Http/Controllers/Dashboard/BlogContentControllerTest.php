<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Dashboard;

use App\Models\BlogCategory;
use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogContentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BlogPostDraft $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $category = BlogCategory::factory()->create();
        $this->draft = BlogPostDraft::factory()->create(['category_id' => $category->id]);
    }

    #[Test]
    public function it_lists_all_content_blocks_for_a_draft(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'content_type',
                    'content_id',
                    'order',
                    'content',
                ],
            ],
        ]);
    }

    #[Test]
    public function it_shows_a_specific_content_block(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $content->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);
    }

    #[Test]
    public function it_returns_404_when_content_does_not_belong_to_draft(): void
    {
        $otherCategory = BlogCategory::factory()->create();
        $otherDraft = BlogPostDraft::factory()->create(['category_id' => $otherCategory->id]);

        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content = $otherDraft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function it_creates_markdown_content(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'translation_key_id' => $translationKey->id,
            'order' => 1,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/markdown", $data);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'content_type',
            'content_id',
            'order',
            'content',
        ]);

        $this->assertDatabaseHas('blog_content_markdown', [
            'translation_key_id' => $translationKey->id,
        ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $this->draft->id,
            'content_type' => BlogContentMarkdown::class,
            'order' => 1,
        ]);
    }

    #[Test]
    public function it_creates_markdown_content_with_auto_order(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();

        // Create existing content with order 1
        $existingMarkdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);
        $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $existingMarkdown->id,
            'order' => 1,
        ]);

        $data = [
            'translation_key_id' => $translationKey->id,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/markdown", $data);

        $response->assertCreated();
        $response->assertJsonFragment(['order' => 2]);
    }

    #[Test]
    public function it_creates_gallery_content(): void
    {
        $pictures = Picture::factory()->count(3)->create();

        $data = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $pictures->pluck('id')->toArray(),
            'order' => 1,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/gallery", $data);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'content_type',
            'content_id',
            'order',
            'content',
        ]);

        $this->assertDatabaseHas('blog_content_galleries', [
            'layout' => 'grid',
            'columns' => 3,
        ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $this->draft->id,
            'content_type' => BlogContentGallery::class,
            'order' => 1,
        ]);
    }

    #[Test]
    public function it_validates_gallery_layout(): void
    {
        $data = [
            'layout' => 'invalid_layout',
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/gallery", $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['layout']);
    }

    #[Test]
    public function it_creates_video_content(): void
    {
        $video = Video::factory()->create();
        $translationKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'video_id' => $video->id,
            'caption_translation_key_id' => $translationKey->id,
            'order' => 1,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/video", $data);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'content_type',
            'content_id',
            'order',
            'content',
        ]);

        $this->assertDatabaseHas('blog_content_videos', [
            'video_id' => $video->id,
            'caption_translation_key_id' => $translationKey->id,
        ]);

        $this->assertDatabaseHas('blog_post_draft_contents', [
            'blog_post_draft_id' => $this->draft->id,
            'content_type' => BlogContentVideo::class,
            'order' => 1,
        ]);
    }

    #[Test]
    public function it_updates_markdown_content(): void
    {
        $originalKey = TranslationKey::factory()->withTranslations()->create();
        $newKey = TranslationKey::factory()->withTranslations()->create();

        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $originalKey->id]);
        $content = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $data = [
            'translation_key_id' => $newKey->id,
        ];

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}/markdown", $data);

        $response->assertOk();
        $this->assertDatabaseHas('blog_content_markdown', [
            'id' => $markdown->id,
            'translation_key_id' => $newKey->id,
        ]);
    }

    #[Test]
    public function it_updates_gallery_content(): void
    {
        $pictures = Picture::factory()->count(2)->create();
        $newPictures = Picture::factory()->count(3)->create();

        $gallery = BlogContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 2,
        ]);
        $gallery->pictures()->attach($pictures->pluck('id')->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index + 1]]));

        $content = $this->draft->contents()->create([
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $data = [
            'layout' => 'masonry',
            'columns' => 4,
            'pictures' => $newPictures->pluck('id')->toArray(),
        ];

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}/gallery", $data);

        $response->assertOk();
        $this->assertDatabaseHas('blog_content_galleries', [
            'id' => $gallery->id,
            'layout' => 'masonry',
            'columns' => 4,
        ]);

        // Check that pictures were updated
        $this->assertEquals(3, $gallery->fresh()->pictures()->count());
    }

    #[Test]
    public function it_updates_video_content(): void
    {
        $originalVideo = Video::factory()->create();
        $newVideo = Video::factory()->create();
        $translationKey = TranslationKey::factory()->withTranslations()->create();

        $videoContent = BlogContentVideo::factory()->create([
            'video_id' => $originalVideo->id,
        ]);
        $content = $this->draft->contents()->create([
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $data = [
            'video_id' => $newVideo->id,
            'caption_translation_key_id' => $translationKey->id,
        ];

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}/video", $data);

        $response->assertOk();
        $this->assertDatabaseHas('blog_content_videos', [
            'id' => $videoContent->id,
            'video_id' => $newVideo->id,
            'caption_translation_key_id' => $translationKey->id,
        ]);
    }

    #[Test]
    public function it_reorders_content_blocks(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown1 = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);
        $markdown2 = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);
        $markdown3 = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content1 = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);
        $content2 = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);
        $content3 = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown3->id,
            'order' => 3,
        ]);

        $data = [
            'order' => [$content3->id, $content1->id, $content2->id],
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/reorder", $data);

        $response->assertOk();

        $this->assertDatabaseHas('blog_post_draft_contents', ['id' => $content3->id, 'order' => 1]);
        $this->assertDatabaseHas('blog_post_draft_contents', ['id' => $content1->id, 'order' => 2]);
        $this->assertDatabaseHas('blog_post_draft_contents', ['id' => $content2->id, 'order' => 3]);
    }

    #[Test]
    public function it_validates_reorder_with_invalid_content_ids(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $data = [
            'order' => [$content->id, 999], // 999 doesn't exist or doesn't belong to this draft
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/reorder", $data);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_deletes_a_content_block(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $response = $this->deleteJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('blog_post_draft_contents', ['id' => $content->id]);
        $this->assertDatabaseMissing('blog_content_markdown', ['id' => $markdown->id]);
    }

    #[Test]
    public function it_duplicates_a_content_block(): void
    {
        $translationKey = TranslationKey::factory()->withTranslations()->create();
        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $translationKey->id]);

        $content = $this->draft->contents()->create([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/{$content->id}/duplicate");

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'content_type',
            'content_id',
            'order',
            'content',
        ]);

        // Check that we now have 2 content blocks
        $this->assertEquals(2, $this->draft->contents()->count());

        // Check that we have 2 markdown content records
        $this->assertEquals(2, BlogContentMarkdown::where('translation_key_id', $translationKey->id)->count());
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents");

        $response->assertUnauthorized();
    }

    #[Test]
    public function content_not_found_returns_404(): void
    {
        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->draft->id}/contents/999");

        $response->assertNotFound();
    }

    #[Test]
    public function draft_not_found_returns_404(): void
    {
        $response = $this->getJson('/dashboard/api/blog/drafts/999/contents');

        $response->assertNotFound();
    }
}
