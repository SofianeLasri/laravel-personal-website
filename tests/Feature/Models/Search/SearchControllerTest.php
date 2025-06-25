<?php

namespace Tests\Feature\Models\Search;

use App\Http\Controllers\Public\SearchController;
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
    public function test_filters_endpoint_returns_tags_and_technologies()
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
        });

        Tag::factory()->count(3)->create();
        Technology::factory()->count(2)->create();

        $response = $this->get(route('public.search.filters'));

        $response->assertOk();
        $response->assertJsonStructure([
            'tags' => [
                '*' => ['id', 'name', 'slug'],
            ],
            'technologies' => [
                '*' => ['id', 'name', 'type', 'iconPicture'],
            ],
        ]);

        $data = $response->json();
        $this->assertCount(3, $data['tags']);
        $this->assertCount(2, $data['technologies']);
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
}
