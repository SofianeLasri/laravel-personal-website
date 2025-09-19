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
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private BlogPost $blogPost;

    private BlogCategory $category;

    private SocialMediaLink $socialMediaLink;

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
    }

    #[Test]
    public function invoke_returns_404_for_non_existent_slug(): void
    {
        $response = $this->get('/blog/article/non-existent-slug');

        $response->assertStatus(404);
    }

    #[Test]
    public function invoke_handles_service_returning_null(): void
    {
        // Mock the service to return null (blog post not found)
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->with('non-existent-slug')
                ->andReturn(null);
        });

        $response = $this->get('/blog/article/non-existent-slug');

        $response->assertStatus(404);
    }

    #[Test]
    public function invoke_uses_correct_error_message_for_404(): void
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn(null);
        });

        $response = $this->get('/blog/article/non-existent-slug');

        $response->assertStatus(404);
        // Verify the 404 message is in French as expected
        $this->assertEquals('Article de blog non trouvé', $response->exception->getMessage());
    }

    #[Test]
    public function invoke_calls_public_service_with_correct_slug(): void
    {
        // Mock the service to verify it's called with the correct slug
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->with($this->blogPost->slug)
                ->andReturn(['id' => $this->blogPost->id, 'slug' => $this->blogPost->slug]);
        });

        $response = $this->get("/blog/article/{$this->blogPost->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function invoke_displays_blog_post_successfully(): void
    {
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->with($this->blogPost->slug)
                ->andReturn([
                    'id' => $this->blogPost->id,
                    'slug' => $this->blogPost->slug,
                    'title' => 'Test Blog Post',
                ]);
        });

        $response = $this->get("/blog/article/{$this->blogPost->slug}");

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
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn([
                    'id' => $this->blogPost->id,
                    'slug' => $this->blogPost->slug,
                ]);
        });

        $response = $this->get("/blog/article/{$this->blogPost->slug}");

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
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn(['id' => $this->blogPost->id]);
        });

        $response = $this->get("/blog/article/{$this->blogPost->slug}");

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
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn(['id' => $this->blogPost->id]);
        });

        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get("/blog/article/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('browserLanguage')
        );
    }

    #[Test]
    public function invoke_includes_social_media_links(): void
    {
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn(['id' => $this->blogPost->id]);
        });

        SocialMediaLink::factory()->count(3)->create();
        $response = $this->get("/blog/article/{$this->blogPost->slug}");

        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 4) // 3 + 1 from setUp
        );
    }

    #[Test]
    public function invoke_works_with_no_social_media_links(): void
    {
        // Mock the service to return blog post data
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn(['id' => $this->blogPost->id]);
        });

        SocialMediaLink::query()->delete();
        $response = $this->get("/blog/article/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 0)
        );
    }

    #[Test]
    public function invoke_passes_blog_post_data_to_view(): void
    {
        $mockBlogPost = [
            'id' => $this->blogPost->id,
            'slug' => $this->blogPost->slug,
            'title' => 'Test Blog Post',
            'content' => 'Test content',
        ];

        $this->mock(PublicControllersService::class, function ($mock) use ($mockBlogPost) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->andReturn($mockBlogPost);
        });

        $response = $this->get("/blog/article/{$this->blogPost->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.id', $mockBlogPost['id'])
            ->where('blogPost.slug', $mockBlogPost['slug'])
            ->where('blogPost.title', $mockBlogPost['title'])
            ->where('blogPost.content', $mockBlogPost['content'])
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
            'slug with underscores' => ['blog_post_with_underscores'],
            'mixed characters' => ['my-blog_post-2024'],
        ];
    }

    #[Test]
    #[DataProvider('slugWithSpecialCharactersProvider')]
    public function invoke_handles_slugs_with_special_characters(string $slug): void
    {
        // Create a blog post with the test slug
        $blogPost = BlogPost::factory()->create(['slug' => $slug]);

        $this->mock(PublicControllersService::class, function ($mock) use ($slug, $blogPost) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->with($slug)
                ->andReturn(['id' => $blogPost->id, 'slug' => $slug]);
        });

        $response = $this->get("/blog/article/{$slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('blogPost.slug', $slug)
        );
    }

    #[Test]
    public function invoke_handles_very_long_slug(): void
    {
        $longSlug = str_repeat('very-long-slug-', 20); // Create a very long slug
        $blogPost = BlogPost::factory()->create(['slug' => $longSlug]);

        $this->mock(PublicControllersService::class, function ($mock) use ($longSlug, $blogPost) {
            $mock->shouldReceive('getBlogPostBySlug')
                ->once()
                ->with($longSlug)
                ->andReturn(['id' => $blogPost->id, 'slug' => $longSlug]);
        });

        $response = $this->get("/blog/article/{$longSlug}");

        $response->assertStatus(200);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}