<?php

namespace Tests\Feature\Controllers\Public;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Models\BlogCategory;
use App\Models\ContentMarkdown;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogHomeControllerTest extends TestCase
{
    use RefreshDatabase;

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
                'slug' => 'test-category-'.uniqid(),
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
                'text' => $attributes['title'] ?? 'Test Blog Post',
            ]);
            Translation::factory()->create([
                'translation_key_id' => $titleKey->id,
                'locale' => 'fr',
                'text' => $attributes['title_fr'] ?? 'Article de Blog Test',
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

        // Clean up attributes that aren't database fields
        unset($attributes['title'], $attributes['title_fr']);

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

        $markdownContent = ContentMarkdown::factory()->create([
            'translation_key_id' => $contentTranslationKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => ContentMarkdown::class,
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
    }

    #[Test]
    public function invoke_returns_404_when_no_blog_posts_exist(): void
    {
        $response = $this->get('/blog');

        $response->assertStatus(404);
        $this->assertEquals('No blog posts found', $response->exception->getMessage());
    }

    #[Test]
    public function invoke_displays_blog_home_with_single_post(): void
    {
        $blogPost = $this->createCompleteBlogPost([
            'category_id' => $this->category->id,
            'title' => 'My Single Blog Post',
            'title_fr' => 'Mon Seul Article de Blog',
        ]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogHome')
            ->has('heroPost')
            ->where('recentPosts', [])
            ->where('hasMultiplePosts', false)
        );
    }

    #[Test]
    public function invoke_displays_blog_home_with_multiple_posts(): void
    {
        // Create 6 blog posts to test hero + recent posts logic
        for ($i = 1; $i <= 6; $i++) {
            $this->createCompleteBlogPost([
                'category_id' => $this->category->id,
                'title' => "Blog Post {$i}",
                'title_fr' => "Article de Blog {$i}",
                'created_at' => now()->subDays(6 - $i), // Newest first
            ]);
        }

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogHome')
            ->has('heroPost')
            ->has('recentPosts', 4) // Should have 4 recent posts (skip(1).take(4))
            ->where('hasMultiplePosts', true)
        );
    }

    #[Test]
    public function invoke_returns_correct_hero_and_recent_posts(): void
    {
        // Create posts with specific creation dates
        $newestPost = $this->createCompleteBlogPost([
            'category_id' => $this->category->id,
            'title' => 'Newest Post',
            'title_fr' => 'Article Le Plus Récent',
            'created_at' => now(),
        ]);

        $secondPost = $this->createCompleteBlogPost([
            'category_id' => $this->category->id,
            'title' => 'Second Post',
            'title_fr' => 'Deuxième Article',
            'created_at' => now()->subDay(),
        ]);

        $thirdPost = $this->createCompleteBlogPost([
            'category_id' => $this->category->id,
            'title' => 'Third Post',
            'title_fr' => 'Troisième Article',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('heroPost.id', $newestPost->id)
            ->where('heroPost.title', 'Article Le Plus Récent') // French locale
            ->has('recentPosts', 2)
            ->where('recentPosts.0.id', $secondPost->id)
            ->where('recentPosts.1.id', $thirdPost->id)
        );
    }

    #[Test]
    public function invoke_passes_correct_locale_and_browser_language(): void
    {
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('locale', 'fr') // Default locale
            ->has('browserLanguage')
        );
    }

    #[Test]
    public function invoke_includes_all_required_translations(): void
    {
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('translations.about')
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
            ->has('translations.projects.types')
        );
    }

    #[Test]
    public function invoke_includes_social_media_links(): void
    {
        SocialMediaLink::factory()->count(3)->create();
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 4) // 3 + 1 from setUp
        );
    }

    #[Test]
    public function invoke_formats_hero_post_correctly(): void
    {
        $blogPost = $this->createCompleteBlogPost([
            'category_id' => $this->category->id,
            'title' => 'Hero Post',
            'title_fr' => 'Article Héros',
        ]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('heroPost.id', $blogPost->id)
            ->where('heroPost.slug', $blogPost->slug)
            ->has('heroPost.title')
            ->has('heroPost.excerpt')
            ->has('heroPost.category')
            ->has('heroPost.coverImage')
            ->has('heroPost.publishedAt')
        );
    }

    #[Test]
    public function invoke_formats_recent_posts_correctly(): void
    {
        // Create multiple posts
        for ($i = 1; $i <= 3; $i++) {
            $this->createCompleteBlogPost([
                'category_id' => $this->category->id,
                'title' => "Post {$i}",
                'title_fr' => "Article {$i}",
                'created_at' => now()->subDays($i),
            ]);
        }

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('recentPosts', 2) // Skip first (hero), take 2
            ->has('recentPosts.0.id')
            ->has('recentPosts.0.title')
            ->has('recentPosts.0.excerpt')
            ->has('recentPosts.0.category')
            ->has('recentPosts.0.coverImage')
            ->has('recentPosts.0.publishedAt')
        );
    }

    #[Test]
    public function invoke_handles_has_multiple_posts_flag_correctly(): void
    {
        // Test with single post
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('hasMultiplePosts', false)
        );

        // Clear and test with multiple posts
        BlogPost::query()->delete();
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('hasMultiplePosts', true)
        );
    }

    #[Test]
    public function invoke_works_with_no_social_media_links(): void
    {
        SocialMediaLink::query()->delete();
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 0)
        );
    }

    #[Test]
    public function invoke_handles_browser_language_detection(): void
    {
        $this->createCompleteBlogPost(['category_id' => $this->category->id]);

        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
        ])->get('/blog');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('browserLanguage')
        );
    }

    /**
     * Data provider for testing blog post order scenarios
     *
     * @return array<string, array{int, string}>
     */
    public static function blogPostOrderProvider(): array
    {
        return [
            'with 1 post' => [1, 'newest post should be hero'],
            'with 2 posts' => [2, 'newest should be hero, second should be in recent'],
            'with 5 posts' => [5, 'newest should be hero, next 4 should be in recent'],
            'with 10 posts' => [10, 'newest should be hero, next 4 should be in recent'],
        ];
    }

    #[Test]
    #[DataProvider('blogPostOrderProvider')]
    public function invoke_respects_blog_post_order(int $postCount, string $description): void
    {
        // Create posts with specific timestamps - newest posts get highest timestamps
        $posts = [];
        for ($i = 0; $i < $postCount; $i++) {
            $posts[] = $this->createCompleteBlogPost([
                'category_id' => $this->category->id,
                'title' => "Post {$i}",
                'title_fr' => "Article {$i}",
                'created_at' => now()->subDays($i), // First post is newest (0 days ago)
            ]);
        }

        $response = $this->get('/blog');

        $response->assertStatus(200);

        // The first created post (index 0) should be the hero because it has newest created_at
        $response->assertInertia(fn ($page) => $page
            ->where('heroPost.id', $posts[0]->id) // First created post is newest
        );

        // Check recent posts count (should be min(postCount - 1, 4))
        $expectedRecentCount = min($postCount - 1, 4);
        $response->assertInertia(fn ($page) => $page
            ->has('recentPosts', $expectedRecentCount)
        );

        // If there are recent posts, verify they're in correct order
        if ($expectedRecentCount > 0) {
            $response->assertInertia(fn ($page) => $page
                ->where('recentPosts.0.id', $posts[1]->id) // Second newest
            );
        }
    }
}
