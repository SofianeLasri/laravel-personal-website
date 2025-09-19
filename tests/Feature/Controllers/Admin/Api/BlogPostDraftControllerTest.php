<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Enums\BlogPostType;
use App\Http\Controllers\Admin\Api\BlogPostDraftController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(BlogPostDraftController::class)]
class BlogPostDraftControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function index_returns_all_drafts_with_relations()
    {
        $category = BlogCategory::factory()->create();
        $coverPicture = Picture::factory()->create();
        $titleKey = TranslationKey::factory()->withTranslations()->create();
        $originalPost = BlogPost::factory()->create();

        $drafts = BlogPostDraft::factory()->count(3)->create([
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture->id,
            'title_translation_key_id' => $titleKey->id,
            'original_blog_post_id' => $originalPost->id,
        ]);

        $response = $this->getJson(route('dashboard.api.blog-post-drafts.index'));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'slug',
                    'type',
                    'category_id',
                    'cover_picture_id',
                    'original_blog_post_id',
                    'title_translation_key_id',
                    'created_at',
                    'updated_at',
                    'title_translation_key' => [
                        'id',
                        'key',
                        'translations',
                    ],
                    'category',
                    'cover_picture',
                    'original_blog_post',
                ],
            ]);
    }

    #[Test]
    public function store_creates_draft_with_all_fields()
    {
        $category = BlogCategory::factory()->create();
        $coverPicture = Picture::factory()->create();
        $originalPost = BlogPost::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'test-blog-post',
            'title_content' => 'Test Blog Post Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture->id,
            'original_blog_post_id' => $originalPost->id,
            'locale' => 'en',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'slug',
                'type',
                'category_id',
                'cover_picture_id',
                'original_blog_post_id',
                'title_translation_key_id',
                'created_at',
                'updated_at',
                'title_translation_key' => [
                    'id',
                    'key',
                    'translations',
                ],
                'category',
                'cover_picture',
                'original_blog_post',
            ])
            ->assertJson([
                'slug' => 'test-blog-post',
                'type' => BlogPostType::ARTICLE->value,
                'category_id' => $category->id,
                'cover_picture_id' => $coverPicture->id,
                'original_blog_post_id' => $originalPost->id,
            ]);

        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => 'test-blog-post',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture->id,
            'original_blog_post_id' => $originalPost->id,
        ]);

        $draft = BlogPostDraft::latest()->first();
        $this->assertNotNull($draft->titleTranslationKey);

        $titleTranslation = $draft->titleTranslationKey->translations()
            ->where('locale', 'en')
            ->first();
        $this->assertEquals('Test Blog Post Title', $titleTranslation->text);
    }

    #[Test]
    public function store_creates_draft_with_required_fields_only()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'minimal-blog-post',
            'title_content' => 'Minimal Title',
            'type' => BlogPostType::GAME_REVIEW->value,
            'category_id' => $category->id,
            'locale' => 'fr',
        ]);

        $response->assertCreated()
            ->assertJson([
                'slug' => 'minimal-blog-post',
                'type' => BlogPostType::GAME_REVIEW->value,
                'category_id' => $category->id,
                'cover_picture_id' => null,
                'original_blog_post_id' => null,
            ]);

        $this->assertDatabaseHas('blog_post_drafts', [
            'slug' => 'minimal-blog-post',
            'type' => BlogPostType::GAME_REVIEW->value,
            'category_id' => $category->id,
            'cover_picture_id' => null,
            'original_blog_post_id' => null,
        ]);
    }

    #[Test]
    public function store_creates_new_translation_key_when_none_provided()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'new-translation-key',
            'title_content' => 'New Translation Key Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $draft = BlogPostDraft::latest()->first();
        $this->assertNotNull($draft->title_translation_key_id);

        $translationKey = $draft->titleTranslationKey;
        $this->assertStringContains('blog_post_draft_title_', $translationKey->key);

        // Verify both locale translations were created
        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'New Translation Key Title',
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'text' => '',
        ]);
    }

    #[Test]
    public function store_updates_existing_translation_key()
    {
        $category = BlogCategory::factory()->create();
        $existingKey = TranslationKey::factory()->withTranslations()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'existing-translation-key',
            'title_content' => 'Updated Title Content',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'title_translation_key_id' => $existingKey->id,
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $draft = BlogPostDraft::latest()->first();
        $this->assertEquals($existingKey->id, $draft->title_translation_key_id);

        // Verify the French translation was updated
        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $existingKey->id,
            'locale' => 'fr',
            'text' => 'Updated Title Content',
        ]);
    }

    #[Test]
    public function store_creates_translations_for_both_locales()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'both-locales',
            'title_content' => 'English Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $draft = BlogPostDraft::latest()->first();
        $translationKey = $draft->titleTranslationKey;

        // Check that both locales exist
        $this->assertEquals(2, $translationKey->translations()->count());

        $enTranslation = $translationKey->translations()->where('locale', 'en')->first();
        $frTranslation = $translationKey->translations()->where('locale', 'fr')->first();

        $this->assertEquals('English Title', $enTranslation->text);
        $this->assertEquals('', $frTranslation->text);
    }

    #[Test]
    public function store_fails_without_required_fields()
    {
        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'title_content', 'type', 'category_id'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function store_fails_with_non_existent_category()
    {
        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'invalid-category',
            'title_content' => 'Test Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => 99999,
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function store_fails_with_non_existent_cover_picture()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'invalid-picture',
            'title_content' => 'Test Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'cover_picture_id' => 99999,
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cover_picture_id']);
    }

    #[Test]
    public function store_fails_with_non_existent_original_blog_post()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'invalid-original-post',
            'title_content' => 'Test Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'original_blog_post_id' => 99999,
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['original_blog_post_id']);
    }

    #[Test]
    public function store_fails_with_invalid_locale()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'invalid-locale',
            'title_content' => 'Test Title',
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'locale' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function store_fails_with_title_too_long()
    {
        $category = BlogCategory::factory()->create();

        $response = $this->postJson(route('dashboard.api.blog-post-drafts.store'), [
            'slug' => 'long-title',
            'title_content' => str_repeat('a', 256), // 256 characters, max is 255
            'type' => BlogPostType::ARTICLE->value,
            'category_id' => $category->id,
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title_content']);
    }

    #[Test]
    public function show_returns_draft_with_all_relations()
    {
        $category = BlogCategory::factory()->create();
        $coverPicture = Picture::factory()->create();
        $titleKey = TranslationKey::factory()->withTranslations()->create();
        $originalPost = BlogPost::factory()->create();

        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture->id,
            'title_translation_key_id' => $titleKey->id,
            'original_blog_post_id' => $originalPost->id,
        ]);

        $response = $this->getJson(route('dashboard.api.blog-post-drafts.show', $draft));

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'slug',
                'type',
                'category_id',
                'cover_picture_id',
                'original_blog_post_id',
                'title_translation_key_id',
                'created_at',
                'updated_at',
                'title_translation_key' => [
                    'id',
                    'key',
                    'translations',
                ],
                'category',
                'cover_picture',
                'original_blog_post',
                'contents',
                'game_review_draft',
            ])
            ->assertJson([
                'id' => $draft->id,
                'slug' => $draft->slug,
                'type' => $draft->type->value,
            ]);
    }

    #[Test]
    public function show_returns_404_for_non_existent_draft()
    {
        $response = $this->getJson(route('dashboard.api.blog-post-drafts.show', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function update_modifies_all_fields_successfully()
    {
        $originalCategory = BlogCategory::factory()->create();
        $newCategory = BlogCategory::factory()->create();
        $newCoverPicture = Picture::factory()->create();
        $titleKey = TranslationKey::factory()->withTranslations()->create();

        $draft = BlogPostDraft::factory()->create([
            'slug' => 'original-slug',
            'type' => BlogPostType::ARTICLE,
            'category_id' => $originalCategory->id,
            'title_translation_key_id' => $titleKey->id,
        ]);

        $response = $this->putJson(
            route('dashboard.api.blog-post-drafts.update', $draft),
            [
                'slug' => 'updated-slug',
                'title_content' => 'Updated Title Content',
                'type' => BlogPostType::GAME_REVIEW->value,
                'category_id' => $newCategory->id,
                'cover_picture_id' => $newCoverPicture->id,
                'locale' => 'en',
            ]
        );

        $response->assertOk()
            ->assertJson([
                'id' => $draft->id,
                'slug' => 'updated-slug',
                'type' => BlogPostType::GAME_REVIEW->value,
                'category_id' => $newCategory->id,
                'cover_picture_id' => $newCoverPicture->id,
            ]);

        $this->assertDatabaseHas('blog_post_drafts', [
            'id' => $draft->id,
            'slug' => 'updated-slug',
            'type' => BlogPostType::GAME_REVIEW->value,
            'category_id' => $newCategory->id,
            'cover_picture_id' => $newCoverPicture->id,
        ]);

        // Check translation was updated
        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Updated Title Content',
        ]);
    }

    #[Test]
    public function update_modifies_translation_for_locale()
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->withTranslations()->create();

        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $titleKey->id,
        ]);

        // Update French translation
        $response = $this->putJson(
            route('dashboard.api.blog-post-drafts.update', $draft),
            [
                'slug' => $draft->slug,
                'title_content' => 'Titre en français',
                'type' => $draft->type->value,
                'category_id' => $category->id,
                'locale' => 'fr',
            ]
        );

        $response->assertOk();

        // Check French translation was updated
        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Titre en français',
        ]);
    }

    #[Test]
    public function update_changes_translation_locale()
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->withTranslations()->create();

        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $titleKey->id,
        ]);

        // First update in English
        $this->putJson(
            route('dashboard.api.blog-post-drafts.update', $draft),
            [
                'slug' => $draft->slug,
                'title_content' => 'English Title',
                'type' => $draft->type->value,
                'category_id' => $category->id,
                'locale' => 'en',
            ]
        )->assertOk();

        // Then update in French
        $response = $this->putJson(
            route('dashboard.api.blog-post-drafts.update', $draft),
            [
                'slug' => $draft->slug,
                'title_content' => 'Titre français',
                'type' => $draft->type->value,
                'category_id' => $category->id,
                'locale' => 'fr',
            ]
        );

        $response->assertOk();

        // Check both translations exist
        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'English Title',
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Titre français',
        ]);
    }

    #[Test]
    public function update_fails_with_validation_errors()
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->putJson(
            route('dashboard.api.blog-post-drafts.update', $draft),
            [
                'slug' => '', // Required but empty
                'title_content' => '', // Required but empty
                'type' => 'invalid_type',
                'category_id' => 99999, // Non-existent
                'locale' => 'invalid_locale',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'title_content', 'type', 'category_id', 'locale'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function destroy_deletes_draft_and_translations()
    {
        $titleKey = TranslationKey::factory()->withTranslations()->create();
        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
        ]);

        $draftId = $draft->id;
        $titleKeyId = $titleKey->id;

        $response = $this->deleteJson(
            route('dashboard.api.blog-post-drafts.destroy', $draft)
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Draft deleted successfully',
            ]);

        // Check draft is deleted
        $this->assertDatabaseMissing('blog_post_drafts', ['id' => $draftId]);

        // Check translation key and translations are deleted
        $this->assertDatabaseMissing('translation_keys', ['id' => $titleKeyId]);
        $this->assertDatabaseMissing('translations', ['translation_key_id' => $titleKeyId]);
    }

    #[Test]
    public function destroy_returns_success_message()
    {
        $draft = BlogPostDraft::factory()->create();

        $response = $this->deleteJson(
            route('dashboard.api.blog-post-drafts.destroy', $draft)
        );

        $response->assertOk()
            ->assertExactJson([
                'message' => 'Draft deleted successfully',
            ]);
    }

    #[Test]
    public function destroy_returns_404_for_non_existent_draft()
    {
        $response = $this->deleteJson(
            route('dashboard.api.blog-post-drafts.destroy', 99999)
        );

        $response->assertNotFound();
    }
}