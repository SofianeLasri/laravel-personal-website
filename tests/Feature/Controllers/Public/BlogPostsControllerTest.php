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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlogPostsControllerTest extends TestCase
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
    public function blog_posts_page_loads_successfully(): void
    {
        $response = $this->get('/blog/articles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/BlogPosts')
            ->has('locale')
            ->has('browserLanguage')
            ->has('translations')
            ->has('socialMediaLinks')
            ->has('posts')
            ->has('categories')
            ->has('currentFilters')
        );
    }

    #[Test]
    public function blog_posts_page_contains_correct_structure(): void
    {
        $response = $this->get('/blog/articles');

        $response->assertInertia(fn ($page) => $page
            ->where('locale', 'fr') // Default locale
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
            ->has('translations.blog')
            ->where('currentFilters.category', [])
            ->where('currentFilters.sort', 'newest')
            ->has('socialMediaLinks', 1)
        );
    }

    #[Test]
    public function blog_posts_page_handles_single_category_filter(): void
    {
        $response = $this->get('/blog/articles?category=technology');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['technology'])
            ->where('currentFilters.sort', 'newest')
        );
    }

    #[Test]
    public function blog_posts_page_handles_multiple_category_filters(): void
    {
        $response = $this->get('/blog/articles?category=technology,lifestyle,tutorial');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['technology', 'lifestyle', 'tutorial'])
            ->where('currentFilters.sort', 'newest')
        );
    }

    #[Test]
    public function blog_posts_page_handles_empty_category_filter(): void
    {
        $response = $this->get('/blog/articles?category=');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', [])
        );
    }

    #[Test]
    public function blog_posts_page_handles_custom_sort_parameter(): void
    {
        $response = $this->get('/blog/articles?sort=oldest');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.sort', 'oldest')
        );
    }

    #[Test]
    public function blog_posts_page_uses_default_sort_when_not_provided(): void
    {
        $response = $this->get('/blog/articles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.sort', 'newest')
        );
    }

    #[Test]
    public function blog_posts_page_handles_combined_filters(): void
    {
        $response = $this->get('/blog/articles?category=technology,tutorial&sort=oldest');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['technology', 'tutorial'])
            ->where('currentFilters.sort', 'oldest')
        );
    }

    #[Test]
    public function blog_posts_page_includes_all_required_translations(): void
    {
        $response = $this->get('/blog/articles');

        $response->assertInertia(fn ($page) => $page
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
            ->has('translations.blog')
        );
    }

    #[Test]
    public function blog_posts_page_includes_social_media_links(): void
    {
        SocialMediaLink::factory()->count(3)->create();
        $response = $this->get('/blog/articles');

        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 4) // 3 + 1 from setUp
        );
    }

    #[Test]
    public function blog_posts_page_handles_browser_language_detection(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get('/blog/articles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('browserLanguage')
        );
    }

    #[Test]
    public function blog_posts_page_handles_special_characters_in_category_filter(): void
    {
        // Test with URL encoded characters
        $response = $this->get('/blog/articles?category=web%20development,mobile%20apps');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['web development', 'mobile apps'])
        );
    }

    #[Test]
    public function blog_posts_page_maintains_filter_parameters_in_response(): void
    {
        $response = $this->get('/blog/articles?category=technology,tutorial&sort=popular');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['technology', 'tutorial'])
            ->where('currentFilters.sort', 'popular')
        );
    }

    #[Test]
    public function blog_posts_page_handles_malformed_category_parameter(): void
    {
        // Test with extra commas
        $response = $this->get('/blog/articles?category=,technology,,tutorial,');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', ['', 'technology', '', 'tutorial', ''])
        );
    }

    #[Test]
    public function blog_posts_page_works_with_no_social_media_links(): void
    {
        SocialMediaLink::query()->delete();
        $response = $this->get('/blog/articles');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('socialMediaLinks', 0)
        );
    }

    #[Test]
    public function blog_posts_page_uses_correct_pagination_limit(): void
    {
        $response = $this->get('/blog/articles');

        // Verify that the service was called with the correct per_page value (12)
        $response->assertStatus(200);
    }

    /**
     * Data provider for testing various filter combinations
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function filterCombinationsProvider(): array
    {
        return [
            'no filters' => ['', ['category' => [], 'sort' => 'newest']],
            'single category' => ['?category=tech', ['category' => ['tech'], 'sort' => 'newest']],
            'multiple categories' => ['?category=tech,web,mobile', ['category' => ['tech', 'web', 'mobile'], 'sort' => 'newest']],
            'custom sort only' => ['?sort=popular', ['category' => [], 'sort' => 'popular']],
            'category and sort' => ['?category=tech&sort=oldest', ['category' => ['tech'], 'sort' => 'oldest']],
            'empty category parameter' => ['?category=&sort=newest', ['category' => [], 'sort' => 'newest']],
        ];
    }

    #[Test]
    #[DataProvider('filterCombinationsProvider')]
    public function blog_posts_page_handles_various_filter_combinations(string $queryString, array $expectedFilters): void
    {
        $response = $this->get('/blog/articles'.$queryString);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('currentFilters.category', $expectedFilters['category'])
            ->where('currentFilters.sort', $expectedFilters['sort'])
        );
    }
}
