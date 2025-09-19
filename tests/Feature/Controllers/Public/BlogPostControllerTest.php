<?php

namespace Tests\Feature\Controllers\Public;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $blogPost;

    private BlogCategory $category;

    private SocialMediaLink $socialMediaLink;

    /**
     * Create a complete blog post with all required relations and content
     */
    private function createCompleteBlogPost(array $attributes = []): BlogPost
    {
        // Create blog category with translation if not provided
        if (! isset($attributes['category_id'])) {
            $categoryNameKey = TranslationKey::factory()->create();
            Translation::factory()->create([
                'translation_key_id' => $categoryNameKey->id,
                'locale' => 'en',
                'text' => 'Test Category',
            ]);
            Translation::factory()->create([
                'translation_key_id' => $categoryNameKey->id,
                'locale' => 'fr',
                'text' => 'Catégorie Test',
            ]);

            $category = BlogCategory::factory()->create([
                'slug' => 'test-category',
                'name_translation_key_id' => $categoryNameKey->id,
                'color' => CategoryColor::BLUE,
                'order' => 1,
            ]);
            $attributes['category_id'] = $category->id;
        }

        // Create title translation if not provided
        if (! isset($attributes['title_translation_key_id'])) {
            $titleKey = TranslationKey::factory()->create();
            Translation::factory()->create([
                'translation_key_id' => $titleKey->id,
                'locale' => 'en',
                'text' => 'Test Blog Post',
            ]);
            Translation::factory()->create([
                'translation_key_id' => $titleKey->id,
                'locale' => 'fr',
                'text' => 'Article de Blog Test',
            ]);
            $attributes['title_translation_key_id'] = $titleKey->id;
        }

        // Create cover picture if not provided
        if (! isset($attributes['cover_picture_id'])) {
            $coverPicture = Picture::factory()->create();
            $attributes['cover_picture_id'] = $coverPicture->id;
        }

        // Set default type if not provided
        if (! isset($attributes['type'])) {
            $attributes['type'] = BlogPostType::ARTICLE;
        }

        // Create the blog post
        $blogPost = BlogPost::factory()->create($attributes);

        // Create blog content - add a simple markdown content
        $contentTranslationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
            'locale' => 'en',
            'text' => 'This is test blog post content in English.',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
            'locale' => 'fr',
            'text' => 'Ceci est le contenu du blog de test en français.',
        ]);

        $markdownContent = \App\Models\BlogContentMarkdown::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
        ]);

        \App\Models\BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => \App\Models\BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        return $blogPost;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create social media links
        $this->socialMediaLink = SocialMediaLink::factory()->create();

        // Create blog category with translation
        $categoryNameKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'en',
            'text' => 'Technology',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $categoryNameKey->id,
            'locale' => 'fr',
            'text' => 'Technologie',
        ]);

        $this->category = BlogCategory::factory()->create([
            'slug' => 'technology',
            'name_translation_key_id' => $categoryNameKey->id,
            'color' => CategoryColor::BLUE,
            'order' => 1,
        ]);

        // Create blog post with translation
        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'My First Blog Post',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Mon Premier Article de Blog',
        ]);

        $coverPicture = Picture::factory()->create();

        $this->blogPost = BlogPost::factory()->create([
            'slug' => 'my-first-blog-post',
            'title_translation_key_id' => $titleKey->id,
            'type' => BlogPostType::ARTICLE,
            'category_id' => $this->category->id,
            'cover_picture_id' => $coverPicture->id,
        ]);

        // Create blog content - add a simple markdown content
        $contentTranslationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
            'locale' => 'en',
            'text' => 'This is test blog post content in English.',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
            'locale' => 'fr',
            'text' => 'Ceci est le contenu du blog de test en français.',
        ]);

        $markdownContent = \App\Models\BlogContentMarkdown::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
        ]);

        \App\Models\BlogPostContent::factory()->create([
            'blog_post_id' => $this->blogPost->id,
            'content_type' => \App\Models\BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);
    }

    #[Test]
    public function invoke_returns_404_for_non_existent_slug(): void
    {
        $response = $this->get('/blog/articles/non-existent-slug');

        $response->assertStatus(404);
    }

    #[Test]
    public function invoke_handles_service_returning_null(): void
    {
        // Test with a slug that doesn't exist in database
        $response = $this->get('/blog/article/definitely-does-not-exist');

        $response->assertStatus(404);
    }

    #[Test]
    public function invoke_uses_correct_error_message_for_404(): void
    {
        $response = $this->get('/blog/articles/non-existent-slug');

        $response->assertStatus(404);
        // Verify the 404 message is in French as expected
        $this->assertEquals('Article de blog non trouvé', $response->exception->getMessage());
    }

    #[Test]
    public function invoke_calls_public_service_with_correct_slug(): void
    {
        // Debug: Let's check if the blog post exists in database
        $this->assertTrue($this->blogPost->exists, 'Blog post should exist in database');
        $this->assertEquals('my-first-blog-post', $this->blogPost->slug, 'Blog post should have correct slug');

        // Check if we can find it directly in database
        $foundPost = BlogPost::where('slug', $this->blogPost->slug)->first();
        $this->assertNotNull($foundPost, 'Should find blog post by slug in database');

        // Test the service directly
        $service = new PublicControllersService;
        $serviceResult = $service->getBlogPostBySlug($this->blogPost->slug);
        $this->assertNotNull($serviceResult, 'Service should find the blog post');

        // This test verifies that the blog post page loads successfully with a valid slug
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogPost')
            ->has('blogPost')
        );
    }

    #[Test]
    public function invoke_displays_blog_post_successfully(): void
    {
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogPost')
            ->has('locale')
            ->has('browserLanguage')
            ->has('translations')
            ->has('socialMediaLinks')
            ->has('blogPost')
        );
    }

    #[Test]
    public function invoke_passes_correct_data_to_view(): void
    {
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertInertia(fn ($page) => $page
            ->where('locale', 'fr') // Default locale
            ->has('browserLanguage')
            ->has('blogPost')
            ->has('socialMediaLinks', 1)
        );
    }

    #[Test]
    public function invoke_includes_all_required_translations(): void
    {
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertInertia(fn ($page) => $page
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
            ->has('translations.blog')
        );
    }

    #[Test]
    public function invoke_handles_browser_language_detection(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('browserLanguage')
        );
    }

    #[Test]
    public function invoke_includes_social_media_links(): void
    {
        SocialMediaLink::factory()->count(3)->create();
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 4) // 3 + 1 from setUp
        );
    }

    #[Test]
    public function invoke_works_with_no_social_media_links(): void
    {
        SocialMediaLink::query()->delete();
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 0)
        );
    }

    #[Test]
    public function invoke_passes_blog_post_data_to_view(): void
    {
        $response = $this->get("/blog/articles/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.id', $this->blogPost->id)
            ->where('blogPost.slug', $this->blogPost->slug)
            ->has('blogPost.title')
        );
    }

    /**
     * Data provider for testing various special characters in slugs
     *
     * @return array<string, array{string}>
     */
    public static function slugWithSpecialCharactersProvider(): array
    {
        return [
            'slug with hyphens' => ['my-blog-post-with-hyphens'],
            'slug with numbers' => ['blog-post-123'],
            'slug with multiple hyphens' => ['blog-post-with-multiple-hyphens'],
            'mixed characters with numbers' => ['my-blog-post-2024'],
        ];
    }

    #[Test]
    #[DataProvider('slugWithSpecialCharactersProvider')]
    public function invoke_handles_slugs_with_special_characters(string $slug): void
    {
        // Create a complete blog post with the test slug
        $blogPost = $this->createCompleteBlogPost(['slug' => $slug]);

        $response = $this->get("/blog/articles/{$slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.slug', $slug)
            ->where('blogPost.id', $blogPost->id)
        );
    }

    #[Test]
    public function invoke_handles_very_long_slug(): void
    {
        $longSlug = str_repeat('very-long-slug-', 20); // Create a very long slug
        $blogPost = $this->createCompleteBlogPost(['slug' => $longSlug]);

        $response = $this->get("/blog/articles/{$longSlug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.slug', $longSlug)
            ->where('blogPost.id', $blogPost->id)
        );
    }
}
