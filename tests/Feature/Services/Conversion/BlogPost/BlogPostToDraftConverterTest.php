<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\BlogPost;

use App\Enums\BlogPostType;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\ContentMarkdown;
use App\Models\GameReview;
use App\Services\Conversion\BlogPost\BlogPostToDraftConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(BlogPostToDraftConverter::class)]
class BlogPostToDraftConverterTest extends TestCase
{
    use RefreshDatabase;

    private BlogPostToDraftConverter $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogPostToDraftConverter::class);
    }

    #[Test]
    public function it_converts_blog_post_to_draft(): void
    {
        $blogPost = BlogPost::factory()->create();

        $draft = $this->service->convert($blogPost);

        $this->assertInstanceOf(BlogPostDraft::class, $draft);
        $this->assertEquals($blogPost->id, $draft->original_blog_post_id);
        $this->assertEquals($blogPost->slug, $draft->slug);
        $this->assertEquals($blogPost->type, $draft->type);
        $this->assertEquals($blogPost->category_id, $draft->category_id);
        $this->assertEquals($blogPost->cover_picture_id, $draft->cover_picture_id);
    }

    #[Test]
    public function it_duplicates_title_translation_key(): void
    {
        $blogPost = BlogPost::factory()->create();

        $draft = $this->service->convert($blogPost);

        // The draft should have a different translation key ID
        $this->assertNotEquals($blogPost->title_translation_key_id, $draft->title_translation_key_id);
    }

    #[Test]
    public function it_throws_exception_when_title_translation_key_missing(): void
    {
        // Create valid blog post first, then modify the attribute in memory
        $blogPost = BlogPost::factory()->create();
        $blogPost->title_translation_key_id = null;
        $blogPost->setRelation('titleTranslationKey', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blog post missing title translation key');

        $this->service->convert($blogPost);
    }

    #[Test]
    public function it_duplicates_blog_post_contents(): void
    {
        $blogPost = BlogPost::factory()->create();
        $markdown = ContentMarkdown::factory()->create();
        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft = $this->service->convert($blogPost);

        $this->assertEquals(1, $draft->contents()->count());
        // Draft content should reference different content IDs (duplicated)
        $draftContent = $draft->contents()->first();
        $this->assertNotEquals($markdown->id, $draftContent->content_id);
    }

    #[Test]
    public function it_duplicates_game_review_if_exists(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
        ]);

        $draft = $this->service->convert($blogPost);

        $this->assertNotNull($draft->gameReviewDraft);
        $this->assertEquals($blogPost->gameReview->game_title, $draft->gameReviewDraft->game_title);
    }

    #[Test]
    public function it_converts_article_without_game_review(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::ARTICLE,
        ]);

        $draft = $this->service->convert($blogPost);

        $this->assertNull($draft->gameReviewDraft);
    }

    #[Test]
    public function it_preserves_content_order(): void
    {
        $blogPost = BlogPost::factory()->create();
        $markdown1 = ContentMarkdown::factory()->create();
        $markdown2 = ContentMarkdown::factory()->create();
        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);

        $draft = $this->service->convert($blogPost);

        $draftContents = $draft->contents()->orderBy('order')->get();
        $this->assertEquals(1, $draftContents[0]->order);
        $this->assertEquals(2, $draftContents[1]->order);
    }
}
