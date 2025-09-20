<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Enums\GameReviewRating;
use App\Http\Controllers\Admin\Api\GameReviewDraftController;
use App\Models\BlogPostDraft;
use App\Models\GameReviewDraft;
use App\Models\GameReviewDraftLink;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(GameReviewDraftController::class)]
class GameReviewDraftControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function store_creates_game_review_draft_with_all_fields()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();
        $coverPicture = Picture::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'The Legend of Zelda: Tears of the Kingdom',
            'release_date' => '2023-05-12',
            'genre' => 'Action-Adventure',
            'developer' => 'Nintendo EPD',
            'publisher' => 'Nintendo',
            'platforms' => ['Nintendo Switch', 'PC'],
            'cover_picture_id' => $coverPicture->id,
            'pros' => 'Excellent gameplay, Beautiful graphics',
            'cons' => 'Performance issues, High price',
            'rating' => 'positive',
            'locale' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'blog_post_draft_id',
                'game_title',
                'release_date',
                'genre',
                'developer',
                'publisher',
                'platforms',
                'cover_picture_id',
                'pros_translation_key_id',
                'cons_translation_key_id',
                'rating',
                'created_at',
                'updated_at',
                'blog_post_draft',
                'cover_picture',
                'pros_translation_key' => [
                    'translations',
                ],
                'cons_translation_key' => [
                    'translations',
                ],
                'links',
            ])
            ->assertJson([
                'blog_post_draft_id' => $blogPostDraft->id,
                'game_title' => 'The Legend of Zelda: Tears of the Kingdom',
                'genre' => 'Action-Adventure',
                'developer' => 'Nintendo EPD',
                'publisher' => 'Nintendo',
                'platforms' => ['Nintendo Switch', 'PC'],
                'cover_picture_id' => $coverPicture->id,
                'rating' => 'positive',
            ]);

        $this->assertDatabaseHas('game_review_drafts', [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'The Legend of Zelda: Tears of the Kingdom',
            'genre' => 'Action-Adventure',
            'rating' => 'positive',
        ]);

        // Verify translations were created
        $gameReviewDraft = GameReviewDraft::latest()->first();
        $this->assertNotNull($gameReviewDraft->prosTranslationKey);
        $this->assertNotNull($gameReviewDraft->consTranslationKey);

        $prosTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('Excellent gameplay, Beautiful graphics', $prosTranslation->text);

        $consTranslation = $gameReviewDraft->consTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('Performance issues, High price', $consTranslation->text);
    }

    #[Test]
    public function store_creates_game_review_draft_with_required_fields_only()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Minimal Game',
            'locale' => 'fr',
        ]);

        $response->assertCreated()
            ->assertJson([
                'blog_post_draft_id' => $blogPostDraft->id,
                'game_title' => 'Minimal Game',
                'release_date' => null,
                'genre' => null,
                'developer' => null,
                'publisher' => null,
                'platforms' => null,
                'cover_picture_id' => null,
                'pros_translation_key_id' => null,
                'cons_translation_key_id' => null,
                'rating' => null,
            ]);

        $this->assertDatabaseHas('game_review_drafts', [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Minimal Game',
            'pros_translation_key_id' => null,
            'cons_translation_key_id' => null,
        ]);
    }

    #[Test]
    public function store_creates_translations_with_french_locale()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Jeu Français',
            'pros' => 'Excellent gameplay, Graphismes magnifiques',
            'cons' => 'Problèmes de performance',
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gameReviewDraft = GameReviewDraft::latest()->first();

        // Check French translations
        $prosTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'fr')
            ->first();
        $this->assertEquals('Excellent gameplay, Graphismes magnifiques', $prosTranslation->text);

        // Check that English translation was created but empty
        $prosTranslationEn = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('', $prosTranslationEn->text);
    }

    #[Test]
    public function store_fails_without_blog_post_draft_id()
    {
        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'game_title' => 'Game Without Blog Post',
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['blog_post_draft_id'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function store_fails_with_non_existent_blog_post_draft()
    {
        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => 99999,
            'game_title' => 'Game with Invalid Blog Post',
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['blog_post_draft_id']);
    }

    #[Test]
    public function store_fails_with_invalid_rating()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with Invalid Rating',
            'rating' => 'excellent', // Invalid value
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    #[Test]
    public function store_fails_with_invalid_locale()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with Invalid Locale',
            'locale' => 'es', // Invalid locale
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function store_fails_with_non_existent_cover_picture()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with Invalid Cover',
            'cover_picture_id' => 99999,
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_picture_id']);
    }

    #[Test]
    public function show_returns_game_review_draft_with_relations()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        GameReviewDraftLink::factory()->count(3)->create([
            'game_review_draft_id' => $gameReviewDraft->id,
        ]);

        $response = $this->getJson(route('dashboard.api.game-review-drafts.show', $gameReviewDraft));

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'blog_post_draft_id',
                'game_title',
                'release_date',
                'genre',
                'developer',
                'publisher',
                'platforms',
                'cover_picture_id',
                'pros_translation_key_id',
                'cons_translation_key_id',
                'rating',
                'created_at',
                'updated_at',
                'blog_post_draft',
                'cover_picture',
                'pros_translation_key' => [
                    'id',
                    'key',
                    'translations',
                ],
                'cons_translation_key' => [
                    'id',
                    'key',
                    'translations',
                ],
                'links',
            ])
            ->assertJson([
                'id' => $gameReviewDraft->id,
                'game_title' => $gameReviewDraft->game_title,
            ])
            ->assertJsonCount(3, 'links');
    }

    #[Test]
    public function show_returns_404_for_non_existent_draft()
    {
        $response = $this->getJson(route('dashboard.api.game-review-drafts.show', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function update_modifies_all_fields_successfully()
    {
        $gameReviewDraft = GameReviewDraft::factory()->withProsAndCons()->create([
            'game_title' => 'Original Title',
            'genre' => 'RPG',
            'rating' => GameReviewRating::POSITIVE,
        ]);

        $newCoverPicture = Picture::factory()->create();

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => 'Updated Title',
                'release_date' => '2024-01-01',
                'genre' => 'Adventure',
                'developer' => 'New Developer',
                'publisher' => 'New Publisher',
                'platforms' => ['PC', 'PS5', 'Xbox'],
                'cover_picture_id' => $newCoverPicture->id,
                'pros' => 'Updated pros text',
                'cons' => 'Updated cons text',
                'rating' => 'negative',
                'locale' => 'en',
            ]
        );

        $response->assertOk()
            ->assertJson([
                'id' => $gameReviewDraft->id,
                'game_title' => 'Updated Title',
                'genre' => 'Adventure',
                'developer' => 'New Developer',
                'publisher' => 'New Publisher',
                'platforms' => ['PC', 'PS5', 'Xbox'],
                'cover_picture_id' => $newCoverPicture->id,
                'rating' => 'negative',
            ]);

        $this->assertDatabaseHas('game_review_drafts', [
            'id' => $gameReviewDraft->id,
            'game_title' => 'Updated Title',
            'genre' => 'Adventure',
            'rating' => 'negative',
        ]);

        // Check translations were updated
        $gameReviewDraft->refresh();
        $prosTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('Updated pros text', $prosTranslation->text);
    }

    #[Test]
    public function update_adds_translations_to_draft_without_them()
    {
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => null,
            'cons_translation_key_id' => null,
        ]);

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'pros' => 'New pros text',
                'cons' => 'New cons text',
                'locale' => 'fr',
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();
        $this->assertNotNull($gameReviewDraft->pros_translation_key_id);
        $this->assertNotNull($gameReviewDraft->cons_translation_key_id);

        // Check French translations
        $prosTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'fr')
            ->first();
        $this->assertEquals('New pros text', $prosTranslation->text);

        $consTranslation = $gameReviewDraft->consTranslationKey->translations()
            ->where('locale', 'fr')
            ->first();
        $this->assertEquals('New cons text', $consTranslation->text);
    }

    #[Test]
    public function update_removes_translations_when_empty_strings_provided()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'pros' => '', // Empty string to remove
                'cons' => '', // Empty string to remove
                'locale' => 'en',
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();
        $this->assertNull($gameReviewDraft->pros_translation_key_id);
        $this->assertNull($gameReviewDraft->cons_translation_key_id);

        // Check that translation keys were deleted
        $this->assertDatabaseMissing('translation_keys', ['id' => $prosKey->id]);
        $this->assertDatabaseMissing('translation_keys', ['id' => $consKey->id]);
    }

    #[Test]
    public function update_preserves_translations_when_not_provided()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => null,
        ]);

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => 'Updated Title Only',
                'locale' => 'en',
                // Not providing pros or cons
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();
        // Pros should be preserved (not deleted)
        $this->assertEquals($prosKey->id, $gameReviewDraft->pros_translation_key_id);
        $this->assertNull($gameReviewDraft->cons_translation_key_id);
    }

    #[Test]
    public function update_fails_with_invalid_data()
    {
        $gameReviewDraft = GameReviewDraft::factory()->create();

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => '', // Required but empty
                'rating' => 'invalid_rating',
                'locale' => 'invalid_locale',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['game_title', 'rating', 'locale'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function destroy_deletes_draft_with_all_relations()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        $links = GameReviewDraftLink::factory()->count(3)->create([
            'game_review_draft_id' => $gameReviewDraft->id,
        ]);

        $draftId = $gameReviewDraft->id;
        $linkIds = $links->pluck('id')->toArray();

        $response = $this->deleteJson(
            route('dashboard.api.game-review-drafts.destroy', $gameReviewDraft)
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Game review draft deleted successfully',
            ]);

        // Check draft is deleted
        $this->assertDatabaseMissing('game_review_drafts', ['id' => $draftId]);

        // Check translation keys are deleted
        $this->assertDatabaseMissing('translation_keys', ['id' => $prosKey->id]);
        $this->assertDatabaseMissing('translation_keys', ['id' => $consKey->id]);

        // Check links are deleted
        foreach ($linkIds as $linkId) {
            $this->assertDatabaseMissing('game_review_draft_links', ['id' => $linkId]);
        }
    }

    #[Test]
    public function destroy_deletes_draft_without_translations()
    {
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => null,
            'cons_translation_key_id' => null,
        ]);

        $draftId = $gameReviewDraft->id;

        $response = $this->deleteJson(
            route('dashboard.api.game-review-drafts.destroy', $gameReviewDraft)
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Game review draft deleted successfully',
            ]);

        $this->assertDatabaseMissing('game_review_drafts', ['id' => $draftId]);
    }

    #[Test]
    public function destroy_returns_404_for_non_existent_draft()
    {
        $response = $this->deleteJson(
            route('dashboard.api.game-review-drafts.destroy', 99999)
        );

        $response->assertNotFound();
    }

    #[Test]
    public function store_validates_platforms_array_items()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with Invalid Platforms',
            'platforms' => ['Valid Platform', 123, null], // Invalid array items
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platforms.1', 'platforms.2']);
    }

    #[Test]
    public function update_changes_translation_locale()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
        ]);

        // First update in English
        $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'pros' => 'English pros text',
                'locale' => 'en',
            ]
        )->assertOk();

        // Then update in French
        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'pros' => 'Texte des avantages en français',
                'locale' => 'fr',
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();

        // Check both translations exist
        $enTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('English pros text', $enTranslation->text);

        $frTranslation = $gameReviewDraft->prosTranslationKey->translations()
            ->where('locale', 'fr')
            ->first();
        $this->assertEquals('Texte des avantages en français', $frTranslation->text);
    }

    #[Test]
    public function update_removes_translations_when_null_provided()
    {
        $prosKey = TranslationKey::factory()->withTranslations()->create();
        $consKey = TranslationKey::factory()->withTranslations()->create();

        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'pros' => null, // Null to remove
                'cons' => null, // Null to remove
                'locale' => 'en',
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();
        $this->assertNull($gameReviewDraft->pros_translation_key_id);
        $this->assertNull($gameReviewDraft->cons_translation_key_id);

        // Check that translation keys were deleted
        $this->assertDatabaseMissing('translation_keys', ['id' => $prosKey->id]);
        $this->assertDatabaseMissing('translation_keys', ['id' => $consKey->id]);
    }

    #[Test]
    public function store_creates_translations_with_empty_cons()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with pros only',
            'pros' => 'Great gameplay',
            'cons' => '', // Empty cons should not create translation
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $gameReviewDraft = GameReviewDraft::latest()->first();
        $this->assertNotNull($gameReviewDraft->prosTranslationKey);
        $this->assertNull($gameReviewDraft->consTranslationKey);
    }

    #[Test]
    public function store_creates_draft_with_only_cons()
    {
        $blogPostDraft = BlogPostDraft::factory()->create();

        $response = $this->postJson(route('dashboard.api.game-review-drafts.store'), [
            'blog_post_draft_id' => $blogPostDraft->id,
            'game_title' => 'Game with cons only',
            'cons' => 'Bad performance',
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $gameReviewDraft = GameReviewDraft::latest()->first();
        $this->assertNull($gameReviewDraft->prosTranslationKey);
        $this->assertNotNull($gameReviewDraft->consTranslationKey);

        // Check French translation
        $consTranslation = $gameReviewDraft->consTranslationKey->translations()
            ->where('locale', 'fr')
            ->first();
        $this->assertEquals('Bad performance', $consTranslation->text);
    }

    #[Test]
    public function update_adds_only_cons_to_draft_without_them()
    {
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'pros_translation_key_id' => null,
            'cons_translation_key_id' => null,
        ]);

        $response = $this->putJson(
            route('dashboard.api.game-review-drafts.update', $gameReviewDraft),
            [
                'game_title' => $gameReviewDraft->game_title,
                'cons' => 'New cons text only',
                'locale' => 'en',
            ]
        );

        $response->assertOk();

        $gameReviewDraft->refresh();
        $this->assertNull($gameReviewDraft->pros_translation_key_id);
        $this->assertNotNull($gameReviewDraft->cons_translation_key_id);

        // Check English translation
        $consTranslation = $gameReviewDraft->consTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('New cons text only', $consTranslation->text);
    }
}
