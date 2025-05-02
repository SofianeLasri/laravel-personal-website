<?php

namespace Tests\Feature\Services;

use App\Models\Creation;
use App\Models\Experience;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PublicControllersService::class)]
class PublicControllersServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_get_creation_count_by_technology()
    {
        Creation::factory()->withTechnologies(5)->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getCreationCountByTechnology();

        $this->assertCount(15, $result);
    }

    #[Test]
    public function test_get_development_stats()
    {
        Creation::factory()->create([
            'started_at' => now()->subYears(2),
            'type' => 'tool',
        ]);

        $service = new PublicControllersService;
        $result = $service->getDevelopmentStats();

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

        $service = new PublicControllersService;
        $result = $service->getLaravelCreations();

        $this->assertCount(3, $result);
        $this->assertEquals('Laravel', $result[0]['technologies'][0]['name']);
    }

    #[Test]
    public function test_get_creations()
    {
        Creation::factory()->withTechnologies()->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getCreations();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    #[Test]
    public function test_format_technology_for_ssr()
    {
        $technology = Technology::factory()->create([
            'name' => 'Laravel',
        ]);

        Creation::factory()->count(3)->afterCreating(function (Creation $creation) use ($technology) {
            $creation->technologies()->attach($technology);
        })->create();

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals($technology->id, $result['id']);
        $this->assertEquals(3, $result['creationCount']);
        $this->assertEquals($technology->name, $result['name']);
        $this->assertEquals($technology->type, $result['type']);
        $this->assertEquals($technology->svg_icon, $result['svgIcon']);
    }

    #[Test]
    public function test_get_technology_experiences()
    {
        TechnologyExperience::factory()->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getTechnologyExperiences();

        $this->assertCount(3, $result);
    }

    #[Test]
    public function test_get_experiences()
    {
        Experience::factory()->count(3)
            ->withTechnologies()
            ->create();

        $service = new PublicControllersService;
        $result = $service->getExperiences();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    #[Test]
    public function test_format_date_with_string()
    {
        $service = new PublicControllersService;

        $date = '01/04/2025';
        $result = $service->formatDate($date);

        $this->assertEquals('Janvier 2025', $result);
        $this->assertNotEquals('01/04/2025', $result);
    }

    #[Test]
    public function test_format_date_with_carbon_object()
    {
        $service = new PublicControllersService;

        $date = now();
        $result = $service->formatDate($date);

        $this->assertEquals(ucfirst(now()->translatedFormat('F Y')), $result);
        $this->assertNotEquals(now(), $result);
    }

    #[Test]
    public function test_format_date_returns_null_if_date_is_null()
    {
        $service = new PublicControllersService;

        $result = $service->formatDate(null);

        $this->assertNull($result);
    }
}
