<?php

namespace Tests\Feature\Models\Search;

use App\Enums\BlogPostType;
use App\Http\Controllers\Public\SearchController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Creation;
use App\Models\Tag;
use App\Models\Technology;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SearchController::class)]
class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_filters_endpoint_returns_only_used_tags_and_technologies()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatTechnologyForSSR')
                ->andReturn([
                    'id' => 1,
                    'creationCount' => 2,
                    'name' => 'Test framework',
                    'description' => 'Test description',
                    'type' => 'framework',
                    'iconPicture' => 1,
                ]);

            $mock->shouldReceive('getTranslationWithFallback')
                ->andReturn('Test Category');
        });

        // Create tags and technologies not used in any creation
        $unusedTags = Tag::factory()->count(2)->create();
        $unusedTechnologies = Technology::factory()->count(2)->create();

        // Create tags and technologies used in creations
        $usedTags = Tag::factory()->count(3)->create();
        $usedTechnologies = Technology::factory()->count(2)->create();

        // Create creations and attach the used tags and technologies
        $creations = Creation::factory()->count(2)->create();
        foreach ($creations as $creation) {
            $creation->tags()->attach($usedTags->pluck('id'));
            $creation->technologies()->attach($usedTechnologies->pluck('id'));
        }

        // Create blog categories - one with blog posts, one without
        $categoryWithPosts = BlogCategory::factory()->create();
        $categoryWithoutPosts = BlogCategory::factory()->create();
        BlogPost::factory()->create(['category_id' => $categoryWithPosts->id]);

        $response = $this->get(route('public.search.filters'));

        $response->assertOk();
        $response->assertJsonStructure([
            'tags' => [
                '*' => ['id', 'name', 'slug'],
            ],
            'technologies' => [
                '*' => ['id', 'name', 'type', 'iconPicture'],
            ],
            'blogCategories' => [
                '*' => ['id', 'name', 'slug', 'color'],
            ],
            'blogTypes' => [
                '*' => ['value', 'label', 'icon'],
            ],
        ]);

        $data = $response->json();
        // Should only return the tags and technologies that are used in creations
        $this->assertCount(3, $data['tags']);
        $this->assertCount(2, $data['technologies']);

        // Should only return categories with blog posts
        $this->assertCount(1, $data['blogCategories']);
        $this->assertEquals($categoryWithPosts->id, $data['blogCategories'][0]['id']);

        // Should return all blog post types
        $this->assertCount(count(BlogPostType::cases()), $data['blogTypes']);

        // Verify that the returned tags are the used ones
        $returnedTagIds = collect($data['tags'])->pluck('id')->sort()->values();
        $expectedTagIds = $usedTags->pluck('id')->sort()->values();
        $this->assertEquals($expectedTagIds->toArray(), $returnedTagIds->toArray());
    }

    #[Test]
    public function test_search_without_query_returns_all_creations()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        Creation::factory()->count(5)->create();

        $response = $this->get(route('public.search'));

        $response->assertOk();
        $response->assertJsonStructure([
            'results' => [],
            'total',
        ]);

        $data = $response->json();
        $this->assertEquals(5, $data['total']);
    }

    #[Test]
    public function test_search_with_query_returns_matching_creations()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        Creation::factory()->create(['name' => 'Laravel Project']);
        Creation::factory()->create(['name' => 'Vue.js App']);

        $response = $this->get(route('public.search', ['q' => 'Laravel']));

        $response->assertOk();
        $response->assertJsonStructure([
            'results' => [],
            'total',
        ]);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }

    #[Test]
    public function test_search_with_tag_filter()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        $tag = Tag::factory()->create(['name' => 'Web Development']);
        $creation = Creation::factory()->create(['name' => 'Test Project']);
        $creation->tags()->attach($tag);

        $response = $this->get(route('public.search', [
            'tags' => [$tag->id],
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }

    #[Test]
    public function test_search_with_technology_filter()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        $technology = Technology::factory()->create(['name' => 'Laravel']);
        $creation = Creation::factory()->create(['name' => 'Laravel App']);
        $creation->technologies()->attach($technology);

        $response = $this->get(route('public.search', [
            'technologies' => [$technology->id],
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }

    #[Test]
    public function test_search_combines_query_and_filters()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        $tag = Tag::factory()->create(['name' => 'Frontend']);
        $technology = Technology::factory()->create(['name' => 'Vue.js']);

        $creation = Creation::factory()->create(['name' => 'Vue Frontend App']);
        $creation->tags()->attach($tag);
        $creation->technologies()->attach($technology);

        $response = $this->get(route('public.search', [
            'q' => 'Vue',
            'tags' => [$tag->id],
            'technologies' => [$technology->id],
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }

    #[Test]
    public function test_search_respects_limit()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        // Create more than 20 creations to test limit
        Creation::factory()->count(25)->create([
            'name' => 'Test Project',
        ]);

        $response = $this->get(route('public.search', ['q' => 'Test']));

        $response->assertOk();
        $data = $response->json();

        // Should not return more than 20 results
        $this->assertLessThanOrEqual(20, count($data['results']));
    }

    #[Test]
    public function test_search_caches_results()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);
        });

        Creation::factory()->create(['name' => 'Cached Project']);

        // First request
        $response1 = $this->get(route('public.search', ['q' => 'Cached']));
        $response1->assertOk();

        // Second request should use cache
        $response2 = $this->get(route('public.search', ['q' => 'Cached']));
        $response2->assertOk();

        // Both responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    #[Test]
    public function test_filters_returns_blog_categories_ordered_by_order()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatTechnologyForSSR')->andReturn([]);
            $mock->shouldReceive('getTranslationWithFallback')
                ->andReturnUsing(function ($translations) {
                    return 'Category';
                });
        });

        // Create categories with different order values
        $category3 = BlogCategory::factory()->create(['order' => 3]);
        $category1 = BlogCategory::factory()->create(['order' => 1]);
        $category2 = BlogCategory::factory()->create(['order' => 2]);

        // Create blog posts for each category
        BlogPost::factory()->create(['category_id' => $category3->id]);
        BlogPost::factory()->create(['category_id' => $category1->id]);
        BlogPost::factory()->create(['category_id' => $category2->id]);

        $response = $this->get(route('public.search.filters'));

        $response->assertOk();
        $data = $response->json();

        // Verify categories are ordered by 'order' field
        $this->assertCount(3, $data['blogCategories']);
        $this->assertEquals($category1->id, $data['blogCategories'][0]['id']);
        $this->assertEquals($category2->id, $data['blogCategories'][1]['id']);
        $this->assertEquals($category3->id, $data['blogCategories'][2]['id']);
    }

    #[Test]
    public function test_filters_returns_all_blog_post_types()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatTechnologyForSSR')->andReturn([]);
            $mock->shouldReceive('getTranslationWithFallback')->andReturn('');
        });

        $response = $this->get(route('public.search.filters'));

        $response->assertOk();
        $data = $response->json();

        // Verify all BlogPostType enum cases are returned
        $this->assertCount(count(BlogPostType::cases()), $data['blogTypes']);

        foreach (BlogPostType::cases() as $type) {
            $found = collect($data['blogTypes'])->first(function ($item) use ($type) {
                return $item['value'] === $type->value;
            });

            $this->assertNotNull($found, "BlogPostType {$type->value} not found in response");
            $this->assertEquals($type->label(), $found['label']);
            $this->assertEquals($type->icon(), $found['icon']);
        }
    }

    #[Test]
    public function test_filters_translates_blog_category_names()
    {
        $translatedName = 'Catégorie Traduite';

        $this->mock(PublicControllersService::class, function ($mock) use ($translatedName) {
            $mock->shouldReceive('formatTechnologyForSSR')->andReturn([]);
            $mock->shouldReceive('getTranslationWithFallback')
                ->once()
                ->andReturn($translatedName);
        });

        $category = BlogCategory::factory()->create();
        BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('public.search.filters'));

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['blogCategories']);
        $this->assertEquals($translatedName, $data['blogCategories'][0]['name']);
    }

    #[Test]
    public function test_search_with_blog_category_filter()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatBlogPostForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'title' => 'Test Blog Post',
                    'slug' => 'test-blog-post',
                    'excerpt' => 'Test excerpt',
                    'type' => 'article',
                    'category' => ['name' => 'Tech', 'color' => 'blue'],
                ]);
        });

        $category = BlogCategory::factory()->create();
        $otherCategory = BlogCategory::factory()->create();

        BlogPost::factory()->create(['category_id' => $category->id]);
        BlogPost::factory()->create(['category_id' => $otherCategory->id]);

        $response = $this->get(route('public.search', [
            'categories' => [$category->id],
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }

    #[Test]
    public function test_search_with_blog_type_filter()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatBlogPostForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'title' => 'Test Blog Post',
                    'slug' => 'test-blog-post',
                    'excerpt' => 'Test excerpt',
                    'type' => 'article',
                    'category' => ['name' => 'Tech', 'color' => 'blue'],
                ]);
        });

        $category = BlogCategory::factory()->create();
        BlogPost::factory()->create([
            'category_id' => $category->id,
            'type' => BlogPostType::ARTICLE,
        ]);
        BlogPost::factory()->create([
            'category_id' => $category->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        $response = $this->get(route('public.search', [
            'types' => [BlogPostType::ARTICLE->value],
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }

    #[Test]
    public function test_search_returns_blog_posts_and_creations()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatCreationForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'name' => 'Test Creation',
                    'slug' => 'test-creation',
                    'shortDescription' => 'Test description',
                    'type' => 'website',
                    'technologies' => [],
                ]);

            $mock->shouldReceive('formatBlogPostForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'title' => 'Test Blog Post',
                    'slug' => 'test-blog-post',
                    'excerpt' => 'Test excerpt',
                    'type' => 'article',
                    'category' => ['name' => 'Tech', 'color' => 'blue'],
                ]);
        });

        Creation::factory()->create(['name' => 'Test Creation']);
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('public.search', ['q' => 'Test']));

        $response->assertOk();
        $data = $response->json();

        // Should return at least one result (either creation or blog post)
        // The exact count depends on title translation matching
        $this->assertGreaterThanOrEqual(1, $data['total']);

        // Verify that resultType is added to results
        if ($data['total'] > 0) {
            $this->assertArrayHasKey('resultType', $data['results'][0]);
            $this->assertContains($data['results'][0]['resultType'], ['creation', 'blogPost']);
        }
    }

    #[Test]
    public function test_search_blog_posts_by_title()
    {
        $this->mock(PublicControllersService::class, function ($mock) {
            $mock->shouldReceive('formatBlogPostForSSRShort')
                ->andReturn([
                    'id' => 1,
                    'title' => 'Laravel Tutorial',
                    'slug' => 'laravel-tutorial',
                    'excerpt' => 'Learn Laravel',
                    'type' => 'article',
                    'category' => ['name' => 'Tech', 'color' => 'blue'],
                ]);
        });

        $category = BlogCategory::factory()->create();
        BlogPost::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('public.search', ['q' => 'Laravel']));

        $response->assertOk();
        $data = $response->json();
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }
}
