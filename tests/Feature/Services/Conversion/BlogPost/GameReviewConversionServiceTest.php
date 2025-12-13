<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\BlogPost;

use App\Enums\BlogPostType;
use App\Enums\GameReviewRating;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\GameReviewLink;
use App\Services\Conversion\BlogPost\GameReviewConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GameReviewConversionService::class)]
class GameReviewConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameReviewConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GameReviewConversionService::class);
    }

    #[Test]
    public function it_creates_draft_from_game_review(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
            'game_title' => 'Test Game',
            'developer' => 'Test Developer',
            'rating' => GameReviewRating::POSITIVE,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        $this->service->createDraftFromReview($gameReview, $draft);

        $draft->refresh();
        $this->assertNotNull($draft->gameReviewDraft);
        $this->assertEquals('Test Game', $draft->gameReviewDraft->game_title);
        $this->assertEquals('Test Developer', $draft->gameReviewDraft->developer);
        $this->assertEquals(GameReviewRating::POSITIVE, $draft->gameReviewDraft->rating);
    }

    #[Test]
    public function it_duplicates_pros_translation_key(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReview = GameReview::factory()->withProsAndCons()->create([
            'blog_post_id' => $blogPost->id,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);

        $this->service->createDraftFromReview($gameReview, $draft);

        $draft->refresh();
        // The draft should have a different translation key ID
        $this->assertNotNull($gameReview->pros_translation_key_id);
        $this->assertNotEquals(
            $gameReview->pros_translation_key_id,
            $draft->gameReviewDraft->pros_translation_key_id
        );
    }

    #[Test]
    public function it_duplicates_cons_translation_key(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReview = GameReview::factory()->withProsAndCons()->create([
            'blog_post_id' => $blogPost->id,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);

        $this->service->createDraftFromReview($gameReview, $draft);

        $draft->refresh();
        $this->assertNotNull($gameReview->cons_translation_key_id);
        $this->assertNotEquals(
            $gameReview->cons_translation_key_id,
            $draft->gameReviewDraft->cons_translation_key_id
        );
    }

    #[Test]
    public function it_duplicates_game_review_links(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
        ]);
        GameReviewLink::factory()->create([
            'game_review_id' => $gameReview->id,
            'url' => 'https://example.com/store',
            'order' => 1,
        ]);
        GameReviewLink::factory()->create([
            'game_review_id' => $gameReview->id,
            'url' => 'https://example.com/website',
            'order' => 2,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
        ]);

        $this->service->createDraftFromReview($gameReview, $draft);

        $draft->refresh();
        $this->assertEquals(2, $draft->gameReviewDraft->links()->count());
    }

    #[Test]
    public function it_syncs_draft_to_new_published_review(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => 'New Game',
            'rating' => GameReviewRating::POSITIVE,
        ]);

        $this->service->syncToPublished($draft, $blogPost);

        $blogPost->refresh();
        $this->assertNotNull($blogPost->gameReview);
        $this->assertEquals('New Game', $blogPost->gameReview->game_title);
        $this->assertEquals(GameReviewRating::POSITIVE, $blogPost->gameReview->rating);
    }

    #[Test]
    public function it_updates_existing_published_review(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $existingReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
            'game_title' => 'Old Title',
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => 'Updated Title',
        ]);

        $this->service->syncToPublished($draft, $blogPost);

        $blogPost->refresh();
        $this->assertEquals($existingReview->id, $blogPost->gameReview->id);
        $this->assertEquals('Updated Title', $blogPost->gameReview->game_title);
    }

    #[Test]
    public function it_deletes_review_if_draft_has_none(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
            'type' => BlogPostType::ARTICLE, // Article doesn't have game review
        ]);
        // No gameReviewDraft for this draft

        $this->service->syncToPublished($draft, $blogPost);

        $blogPost->refresh();
        $this->assertNull($blogPost->gameReview);
    }

    #[Test]
    public function it_syncs_links_from_draft_to_published(): void
    {
        $blogPost = BlogPost::factory()->create([
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
        ]);
        $draft = BlogPostDraft::factory()->forBlogPost($blogPost)->create([
            'original_blog_post_id' => $blogPost->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);
        $gameReviewDraft = GameReviewDraft::factory()->withProsAndCons()->create([
            'blog_post_draft_id' => $draft->id,
        ]);
        $gameReviewDraft->links()->create([
            'type' => 'store',
            'url' => 'https://example.com/new-store',
            'label_translation_key_id' => $gameReviewDraft->pros_translation_key_id,
            'order' => 1,
        ]);

        $this->service->syncLinks($gameReviewDraft, $gameReview);

        $gameReview->refresh();
        $this->assertEquals(1, $gameReview->links()->count());
        $this->assertEquals('https://example.com/new-store', $gameReview->links()->first()->url);
    }
}
