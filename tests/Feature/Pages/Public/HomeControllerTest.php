<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\HomeController;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('public/Home')
                    ->has('socialMediaLinks')
                    ->has('yearsOfExperience')
                    ->has('developmentCreationsCount')
                    ->has('technologiesCount')
                    ->has('laravelCreations')
                    ->has('technologyExperiences')
                    ->has('experiences')
            );
    }

    #[TestDox('It successfully preloads the creation counts by technology')]
    public function test_preload_creation_counts_by_technology()
    {
        Creation::factory()->withTechnologies()->count(3)->create();

        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);

        $method = $reflection->getMethod('preloadCreationCountsByTechnology');

        $method->invoke($controller);
        $creationCountByTechnology = $reflection->getProperty('creationCountByTechnology');

        $this->assertNotEmpty($creationCountByTechnology->getValue($controller));
    }

    #[TestDox('It successfully calc the development years of experience')]
    public function test_get_development_stats()
    {
        Creation::factory()->create([
            'started_at' => now()->subYears(2),
            'type' => 'tool',
        ]);

        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);

        $method = $reflection->getMethod('getDevelopmentStats');

        $result = $method->invoke($controller);
        $this->assertArrayHasKey('yearsOfExperience', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(2, $result['yearsOfExperience']);
        $this->assertEquals(1, $result['count']);
    }

    #[Test]
    public function test_get_laravel_creations()
    {
        $laravelTech = Technology::factory()->create([
            'name' => 'Laravel',
        ]);

        $laravelCreations = Creation::factory()->count(3)->create([
            'type' => 'website',
        ]);

        foreach ($laravelCreations as $creation) {
            $creation->technologies()->attach($laravelTech);
        }

        Creation::factory()->count(2)->create([
            'type' => 'website',
        ]);

        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getLaravelCreations');

        $result = $method->invoke($controller);

        $this->assertCount(3, $result);
        $this->assertEquals('Laravel', $result[0]['technologies'][0]['name']);
    }

    #[Test]
    public function test_get_technology_experiences()
    {
        TechnologyExperience::factory()->count(3)->create();

        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getTechnologyExperiences');

        $result = $method->invoke($controller);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function test_get_experiences()
    {
        Experience::factory()->count(3)
            ->withTechnologies()
            ->create();

        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getExperiences');

        $result = $method->invoke($controller);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    #[Test]
    public function test_format_date_with_string()
    {
        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('formatDate');

        $date = '01/04/2025';
        $result = $method->invoke($controller, $date);

        $this->assertEquals('Janvier 2025', $result);
        $this->assertNotEquals('01/04/2025', $result);
    }

    #[Test]
    public function test_format_date_with_carbon_object()
    {
        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('formatDate');

        $date = now();
        $result = $method->invoke($controller, $date);

        $this->assertEquals(ucfirst(now()->translatedFormat('F Y')), $result);
        $this->assertNotEquals(now(), $result);
    }

    #[Test]
    public function test_format_date_returns_null_if_date_is_null()
    {
        $controller = new HomeController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('formatDate');

        $date = null;
        $result = $method->invoke($controller, $date);

        $this->assertNull($result);
    }
}
