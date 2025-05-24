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
        $result = $service->calcCreationCountByTechnology();

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
    public function test_get_laravel_creations_but_laravel_tech_doesnt_exists()
    {
        Creation::factory()->count(3)->create([
            'type' => 'website',
        ]);

        $service = new PublicControllersService;
        $result = $service->getLaravelCreations();

        $this->assertCount(0, $result);
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

    #[Test]
    public function test_format_creation_for_ssr_short()
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'type' => 'website',
            'started_at' => now(),
            'ended_at' => now()->addMonth(),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRShort($creation);

        $this->assertEquals($creation->id, $result['id']);
        $this->assertEquals($creation->name, $result['name']);
        $this->assertEquals($creation->type, $result['type']);
        $this->assertEquals($creation->slug, $result['slug']);
        $this->assertEquals($creation->started_at, $result['startedAt']);
        $this->assertEquals($creation->ended_at, $result['endedAt']);
        $this->assertArrayHasKey('technologies', $result);
        $this->assertCount($creation->technologies->count(), $result['technologies']);

        foreach ($creation->technologies as $technology) {
            $resultTechnology = collect($result['technologies'])->firstWhere('id', $technology->id);

            $this->assertEquals($technology->id, $resultTechnology['id']);
            $this->assertEquals($technology->name, $resultTechnology['name']);
            $this->assertEquals($technology->type, $resultTechnology['type']);
            $this->assertEquals($technology->svg_icon, $resultTechnology['svgIcon']);
        }

        $this->assertArrayHasKey('logo', $result);
        $this->assertEquals($creation->logo->filename, $result['logo']['filename']);

        $this->assertArrayHasKey('coverImage', $result);
        $this->assertEquals($creation->coverImage->filename, $result['coverImage']['filename']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full()
    {
        $creation = Creation::factory()
            ->withFeatures(3)
            ->withScreenshots(4)
            ->withPeople(2)
            ->create([
                'name' => 'Test Creation',
                'type' => 'website',
                'started_at' => now(),
                'ended_at' => now()->addMonth(),
                'external_url' => 'https://example.com',
                'source_code_url' => 'https://github.com/example/repo',
            ]);

        $this->assertCount(3, $creation->features);
        $this->assertCount(4, $creation->screenshots);

        $featureWithoutPicture = $creation->features->first();
        $featureWithoutPicture->update(['picture_id' => null]);

        $screenshotWithoutCaption = $creation->screenshots->first();
        $screenshotWithoutCaption->update(['caption_translation_key_id' => null]);

        $creation->refresh();

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertEquals($creation->external_url, $result['externalUrl']);
        $this->assertEquals($creation->source_code_url, $result['sourceCodeUrl']);
        $this->assertCount($creation->features->count(), $result['features']);
        $this->assertCount($creation->screenshots->count(), $result['screenshots']);

        foreach ($creation->features as $feature) {
            $resultFeature = collect($result['features'])->firstWhere('id', $feature->id);

            $this->assertEquals($feature->id, $resultFeature['id']);

            $featureName = $feature->titleTranslationKey->translations->firstWhere('locale', app()->getLocale())->text;
            $featureDescription = $feature->descriptionTranslationKey->translations->firstWhere('locale', app()->getLocale())->text;

            $this->assertEquals($featureName, $resultFeature['title']);
            $this->assertEquals($featureDescription, $resultFeature['description']);

            if ($feature->picture_id) {
                $this->assertNotNull($resultFeature['picture']);
                $this->assertEquals($feature->picture->filename, $resultFeature['picture']['filename']);
                $this->assertArrayHasKey('avif', $resultFeature['picture']);
                $this->assertArrayHasKey('webp', $resultFeature['picture']);
            } else {
                $this->assertNull($resultFeature['picture']);
            }
        }

        foreach ($creation->screenshots as $screenshot) {
            $resultScreenshot = collect($result['screenshots'])->firstWhere('id', $screenshot->id);

            $this->assertEquals($screenshot->id, $resultScreenshot['id']);

            if ($screenshot->captionTranslationKey) {
                $caption = $screenshot->captionTranslationKey->translations->firstWhere('locale', app()->getLocale())->text;
                $this->assertEquals($caption, $resultScreenshot['caption']);
            } else {
                $this->assertEmpty($resultScreenshot['caption']);
            }

            $this->assertNotNull($resultScreenshot['picture']);
            $this->assertEquals($screenshot->picture->filename, $resultScreenshot['picture']['filename']);

            $this->assertArrayHasKey('avif', $resultScreenshot['picture']);
            $this->assertArrayHasKey('webp', $resultScreenshot['picture']);
        }

        foreach ($creation->people as $person) {
            $resultPerson = collect($result['people'])->firstWhere('id', $person->id);

            $this->assertEquals($person->id, $resultPerson['id']);
            $this->assertEquals($person->name, $resultPerson['name']);
            $this->assertEquals($person->url, $resultPerson['url']);
            $this->assertEquals($person->picture->filename, $resultPerson['picture']['filename']);
        }
    }
}
