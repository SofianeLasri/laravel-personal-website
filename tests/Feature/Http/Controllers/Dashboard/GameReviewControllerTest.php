<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Dashboard;

use App\Models\BlogCategory;
use App\Models\BlogPostDraft;
use App\Models\GameReviewDraft;
use App\Models\GameReviewDraftLink;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BlogPostDraft $gameReviewDraft;

    private BlogPostDraft $articleDraft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $category = BlogCategory::factory()->create();
        $this->gameReviewDraft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'type' => 'game_review',
        ]);
        $this->articleDraft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'type' => 'article',
        ]);
    }

    #[Test]
    public function it_shows_game_review_for_draft(): void
    {
        $picture = Picture::factory()->create();
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
            'game_title' => 'Test Game',
            'release_date' => '2023-01-15',
            'genre' => 'Action RPG',
            'developer' => 'Test Studio',
            'publisher' => 'Test Publisher',
            'platforms' => ['PC', 'PlayStation 5'],
            'cover_picture_id' => $picture->id,
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
            'score' => 8.5,
        ]);

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review");

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $gameReview->id,
            'game_title' => 'Test Game',
            'release_date' => '2023-01-15',
            'genre' => 'Action RPG',
            'developer' => 'Test Studio',
            'publisher' => 'Test Publisher',
            'platforms' => ['PC', 'PlayStation 5'],
            'score' => '8.5',
        ]);
    }

    #[Test]
    public function it_returns_422_when_draft_is_not_game_review_type(): void
    {
        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->articleDraft->id}/game-review");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Draft is not a game review']);
    }

    #[Test]
    public function it_returns_404_when_game_review_does_not_exist(): void
    {
        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review");

        $response->assertNotFound();
        $response->assertJsonFragment(['message' => 'Game review not found']);
    }

    #[Test]
    public function it_creates_game_review_for_draft(): void
    {
        $picture = Picture::factory()->create();
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'game_title' => 'New Test Game',
            'release_date' => '2024-03-20',
            'genre' => 'Platform',
            'developer' => 'New Studio',
            'publisher' => 'New Publisher',
            'platforms' => ['PC', 'Xbox Series X'],
            'cover_picture_id' => $picture->id,
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
            'score' => 7.8,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review", $data);

        $response->assertCreated();
        $response->assertJsonFragment([
            'game_title' => 'New Test Game',
            'release_date' => '2024-03-20',
            'genre' => 'Platform',
            'developer' => 'New Studio',
            'publisher' => 'New Publisher',
            'platforms' => ['PC', 'Xbox Series X'],
            'score' => '7.8',
        ]);

        $this->assertDatabaseHas('game_review_drafts', [
            'blog_post_draft_id' => $this->gameReviewDraft->id,
            'game_title' => 'New Test Game',
            'genre' => 'Platform',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating_game_review(): void
    {
        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['game_title']);
    }

    #[Test]
    public function it_validates_score_range(): void
    {
        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review", [
            'game_title' => 'Test Game',
            'score' => 15.0, // Invalid score > 10
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['score']);
    }

    #[Test]
    public function it_prevents_creating_duplicate_game_reviews(): void
    {
        GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
            'game_title' => 'Existing Game',
        ]);

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review", [
            'game_title' => 'New Game',
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'Game review already exists for this draft']);
    }

    #[Test]
    public function it_updates_game_review(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
            'game_title' => 'Original Title',
            'genre' => 'RPG',
            'score' => 7.0,
        ]);

        $data = [
            'game_title' => 'Updated Title',
            'genre' => 'Action',
            'score' => 8.5,
        ];

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review", $data);

        $response->assertOk();
        $response->assertJsonFragment([
            'game_title' => 'Updated Title',
            'genre' => 'Action',
            'score' => '8.5',
        ]);

        $this->assertDatabaseHas('game_review_drafts', [
            'id' => $gameReview->id,
            'game_title' => 'Updated Title',
            'genre' => 'Action',
            'score' => 8.5,
        ]);
    }

    #[Test]
    public function it_deletes_game_review(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);

        // Add some links to test cascading delete
        $labelKey = TranslationKey::factory()->withTranslations()->create();
        GameReviewDraftLink::factory()->count(2)->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
        ]);

        $response = $this->deleteJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review");

        $response->assertNoContent();
        $this->assertDatabaseMissing('game_review_drafts', ['id' => $gameReview->id]);
        $this->assertDatabaseMissing('game_review_draft_links', ['game_review_draft_id' => $gameReview->id]);
    }

    #[Test]
    public function it_creates_game_review_link(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'type' => 'steam',
            'url' => 'https://store.steampowered.com/app/12345',
            'label_translation_key_id' => $labelKey->id,
            'order' => 1,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links", $data);

        $response->assertCreated();
        $response->assertJsonFragment([
            'type' => 'steam',
            'url' => 'https://store.steampowered.com/app/12345',
            'order' => 1,
        ]);

        $this->assertDatabaseHas('game_review_draft_links', [
            'game_review_draft_id' => $gameReview->id,
            'type' => 'steam',
            'url' => 'https://store.steampowered.com/app/12345',
        ]);
    }

    #[Test]
    public function it_validates_link_type(): void
    {
        GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'type' => 'invalid_type',
            'url' => 'https://example.com',
            'label_translation_key_id' => $labelKey->id,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links", $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function it_validates_link_url_format(): void
    {
        GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $data = [
            'type' => 'steam',
            'url' => 'invalid-url',
            'label_translation_key_id' => $labelKey->id,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links", $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['url']);
    }

    #[Test]
    public function it_auto_assigns_order_when_creating_link(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        // Create existing link with order 1
        GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
            'order' => 1,
        ]);

        $data = [
            'type' => 'playstation',
            'url' => 'https://store.playstation.com/game/12345',
            'label_translation_key_id' => $labelKey->id,
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links", $data);

        $response->assertCreated();
        $response->assertJsonFragment(['order' => 2]);
    }

    #[Test]
    public function it_updates_game_review_link(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();
        $newLabelKey = TranslationKey::factory()->withTranslations()->create();

        $link = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'type' => 'steam',
            'url' => 'https://old-url.com',
            'label_translation_key_id' => $labelKey->id,
        ]);

        $data = [
            'type' => 'gog',
            'url' => 'https://new-url.com',
            'label_translation_key_id' => $newLabelKey->id,
        ];

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links/{$link->id}", $data);

        $response->assertOk();
        $response->assertJsonFragment([
            'type' => 'gog',
            'url' => 'https://new-url.com',
            'label_translation_key_id' => $newLabelKey->id,
        ]);

        $this->assertDatabaseHas('game_review_draft_links', [
            'id' => $link->id,
            'type' => 'gog',
            'url' => 'https://new-url.com',
        ]);
    }

    #[Test]
    public function it_deletes_game_review_link(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $link = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
        ]);

        $response = $this->deleteJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links/{$link->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('game_review_draft_links', ['id' => $link->id]);
    }

    #[Test]
    public function it_returns_404_when_link_does_not_belong_to_game_review(): void
    {
        $otherGameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);

        $category = BlogCategory::factory()->create();
        $otherDraft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'type' => 'game_review',
        ]);
        $otherGameReviewDraft = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $otherDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $link = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $otherGameReviewDraft->id,
            'label_translation_key_id' => $labelKey->id,
        ]);

        $response = $this->putJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links/{$link->id}", [
            'type' => 'gog',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_reorders_game_review_links(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $link1 = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
            'order' => 1,
        ]);
        $link2 = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
            'order' => 2,
        ]);
        $link3 = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
            'order' => 3,
        ]);

        $data = [
            'order' => [$link3->id, $link1->id, $link2->id],
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links/reorder", $data);

        $response->assertOk();

        $this->assertDatabaseHas('game_review_draft_links', ['id' => $link3->id, 'order' => 1]);
        $this->assertDatabaseHas('game_review_draft_links', ['id' => $link1->id, 'order' => 2]);
        $this->assertDatabaseHas('game_review_draft_links', ['id' => $link2->id, 'order' => 3]);
    }

    #[Test]
    public function it_validates_reorder_with_invalid_link_ids(): void
    {
        $gameReview = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $this->gameReviewDraft->id,
        ]);
        $labelKey = TranslationKey::factory()->withTranslations()->create();

        $link = GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReview->id,
            'label_translation_key_id' => $labelKey->id,
        ]);

        $data = [
            'order' => [$link->id, 999], // 999 doesn't exist or doesn't belong to this game review
        ];

        $response = $this->postJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review/links/reorder", $data);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson("/dashboard/api/blog/drafts/{$this->gameReviewDraft->id}/game-review");

        $response->assertUnauthorized();
    }

    #[Test]
    public function draft_not_found_returns_404(): void
    {
        $response = $this->getJson('/dashboard/api/blog/drafts/999/game-review');

        $response->assertNotFound();
    }
}
