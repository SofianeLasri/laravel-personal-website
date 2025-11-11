<?php

namespace Tests\Feature\Controllers\Public;

use App\Enums\BlogPostType;
use App\Http\Controllers\Public\BlogPostPreviewController;
use App\Models\BlogCategory;
use App\Models\ContentMarkdown;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\BlogPostPreviewToken;
use App\Models\GameReviewDraft;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostPreviewController::class)]
class BlogPostPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCompleteBlogPostDraft(array $attributes = []): BlogPostDraft
    {
        // Create category
        if (! isset($attributes['category_id'])) {
            $categoryNameKey = TranslationKey::factory()->create();
            Translation::factory()->create([
                'translation_key_id' => $categoryNameKey->id,
                'locale' => 'fr',
                'text' => 'Catégorie Test',
            ]);

            $category = BlogCategory::factory()->create([
                'name_translation_key_id' => $categoryNameKey->id,
            ]);
            $attributes['category_id'] = $category->id;
        }

        // Create title translation
        if (! isset($attributes['title_translation_key_id'])) {
            $titleKey = TranslationKey::factory()->create();
            Translation::factory()->create([
                'translation_key_id' => $titleKey->id,
                'locale' => 'fr',
                'text' => 'Brouillon de Test',
            ]);
            $attributes['title_translation_key_id'] = $titleKey->id;
        }

        // Create cover picture
        if (! isset($attributes['cover_picture_id'])) {
            $coverPicture = Picture::factory()->create();
            $attributes['cover_picture_id'] = $coverPicture->id;
        }

        if (! isset($attributes['type'])) {
            $attributes['type'] = BlogPostType::ARTICLE;
        }

        $draft = BlogPostDraft::factory()->create($attributes);

        // Add markdown content
        $contentKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $contentKey->id,
            'locale' => 'fr',
            'text' => 'Contenu de test en français',
        ]);

        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $contentKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        return $draft;
    }

    #[Test]
    public function invoke_displays_blog_post_preview_with_valid_token(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogPost')
            ->has('blogPost')
            ->has('locale')
            ->has('browserLanguage')
            ->has('translations')
            ->has('socialMediaLinks')
        );
    }

    #[Test]
    public function invoke_returns_404_for_invalid_token(): void
    {
        $response = $this->get('/blog/preview/invalid-token-123');

        $response->assertStatus(404);
        $this->assertEquals('Lien de prévisualisation non trouvé', $response->exception->getMessage());
    }

    #[Test]
    public function invoke_returns_404_for_expired_token(): void
    {
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::factory()->expired()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(404);
        $this->assertEquals('Ce lien de prévisualisation a expiré', $response->exception->getMessage());
    }

    #[Test]
    public function invoke_passes_blog_post_data_with_is_preview_flag(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.id', $draft->id)
            ->where('blogPost.slug', $draft->slug)
            ->where('blogPost.isPreview', true)
            ->has('blogPost.title')
            ->has('blogPost.contents')
        );
    }

    #[Test]
    public function invoke_includes_all_required_translations(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertInertia(fn ($page) => $page
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
            ->has('translations.blog')
        );
    }

    #[Test]
    public function invoke_includes_social_media_links(): void
    {
        SocialMediaLink::factory()->count(3)->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 3)
        );
    }

    #[Test]
    public function invoke_handles_browser_language_detection(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('browserLanguage')
        );
    }

    #[Test]
    public function invoke_displays_preview_with_game_review_type(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft(['type' => BlogPostType::GAME_REVIEW]);

        // Add game review data
        GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $draft->id,
        ]);

        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.type', BlogPostType::GAME_REVIEW->value)
            ->has('blogPost.gameReview')
        );
    }

    #[Test]
    public function invoke_sets_prevent_indexing_header(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    #[Test]
    public function invoke_works_with_no_social_media_links(): void
    {
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 0)
        );
    }

    #[Test]
    public function invoke_passes_correct_locale(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertInertia(fn ($page) => $page
            ->where('locale', 'fr') // Default locale
        );
    }

    #[Test]
    public function invoke_loads_draft_with_all_relations(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('blogPost.category')
            ->has('blogPost.coverImage')
            ->has('blogPost.contents')
        );
    }

    #[Test]
    public function invoke_returns_404_when_token_exists_but_draft_deleted(): void
    {
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        // Delete the draft
        $draft->delete();

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(404);
    }

    #[Test]
    public function invoke_uses_blog_post_component(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogPost')
        );
    }

    #[Test]
    public function invoke_includes_cover_picture_data(): void
    {
        SocialMediaLink::factory()->create();
        $coverPicture = Picture::factory()->create();
        $draft = $this->createCompleteBlogPostDraft(['cover_picture_id' => $coverPicture->id]);
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('blogPost.coverImage.filename')
            ->has('blogPost.coverImage.avif')
            ->has('blogPost.coverImage.webp')
            ->has('blogPost.coverImage.jpg')
        );
    }

    #[Test]
    public function invoke_includes_category_data(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('blogPost.category')
        );
    }

    #[Test]
    public function invoke_is_publicly_accessible_without_authentication(): void
    {
        SocialMediaLink::factory()->create();
        $draft = $this->createCompleteBlogPostDraft();
        $token = BlogPostPreviewToken::createForDraft($draft);

        // Ensure no authentication
        $this->assertGuest();

        $response = $this->get("/blog/preview/{$token->token}");

        $response->assertStatus(200);
    }
}
