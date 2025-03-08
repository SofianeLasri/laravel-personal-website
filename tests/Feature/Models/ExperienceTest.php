<?php

namespace Tests\Feature\Models;

use App\Enums\ExperienceType;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Experience::class)]
class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_experience()
    {
        $titleKey = TranslationKey::factory()->create();
        $shortDescKey = TranslationKey::factory()->create();
        $fullDescKey = TranslationKey::factory()->create();

        $experience = Experience::create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Université Paris Saclay',
            'type' => ExperienceType::FORMATION,
            'location' => 'Saclay, France',
            'website_url' => 'https://www.universite-paris-saclay.fr/',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => '2018-09-01',
            'ended_at' => '2020-07-01',
        ]);

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'organization_name' => 'Université Paris Saclay',
            'type' => 'formation',
            'location' => 'Saclay, France',
        ]);

        $this->assertInstanceOf(ExperienceType::class, $experience->type);
        $this->assertEquals(ExperienceType::FORMATION, $experience->type);
    }

    #[Test]
    public function it_can_create_an_ongoing_experience()
    {
        $experience = Experience::factory()->ongoing()->create();

        $this->assertNull($experience->ended_at);
        $this->assertTrue($experience->isOngoing());
    }

    #[Test]
    public function it_can_have_technologies()
    {
        $experience = Experience::factory()->create();
        $technologies = Technology::factory()->count(5)->create();

        $experience->technologies()->attach($technologies);

        $this->assertCount(5, $experience->technologies);
        $this->assertInstanceOf(Technology::class, $experience->technologies->first());
    }

    #[Test]
    public function it_can_be_filtered_by_type()
    {
        Experience::factory()->count(3)->formation()->create();
        Experience::factory()->count(2)->emploi()->create();

        $formations = Experience::ofType(ExperienceType::FORMATION)->get();
        $emplois = Experience::ofType(ExperienceType::EMPLOI)->get();

        $this->assertCount(3, $formations);
        $this->assertCount(2, $emplois);
    }

    #[Test]
    public function it_can_be_sorted_by_date()
    {
        $older = Experience::factory()->create([
            'started_at' => '2015-01-01',
        ]);

        $newer = Experience::factory()->create([
            'started_at' => '2020-01-01',
        ]);

        $experiences = Experience::latest()->get();

        $this->assertEquals($newer->id, $experiences->first()->id);
        $this->assertEquals($older->id, $experiences->last()->id);
    }

    #[Test]
    public function it_can_get_translated_fields()
    {
        $titleKey = TranslationKey::create(['key' => 'experience.title.test']);

        Translation::create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Développeur Full-Stack',
        ]);

        Translation::create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Full-Stack Developer',
        ]);

        $experience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
        ]);

        $this->assertEquals('Développeur Full-Stack', $experience->getTitle('fr'));
        $this->assertEquals('Full-Stack Developer', $experience->getTitle('en'));
    }

    #[Test]
    public function it_can_have_descriptions()
    {
        $shortDescKey = TranslationKey::create(['key' => 'experience.short_description.test']);
        $fullDescKey = TranslationKey::create(['key' => 'experience.full_description.test']);

        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Développement d\'applications web',
        ]);

        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'en',
            'text' => 'Web applications development',
        ]);

        Translation::create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Développement de plusieurs applications web',
        ]);

        Translation::create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'en',
            'text' => 'Development of several web applications',
        ]);

        $experience = Experience::factory()->create([
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $this->assertEquals('Développement d\'applications web', $experience->getShortDescription('fr'));
        $this->assertEquals('Web applications development', $experience->getShortDescription('en'));

        $this->assertEquals('Développement de plusieurs applications web', $experience->getFullDescription('fr'));
        $this->assertEquals('Development of several web applications', $experience->getFullDescription('en'));
    }

    #[Test]
    public function it_can_have_logo()
    {
        $logo = Picture::factory()->create();
        $experience = Experience::factory()->create(['logo_id' => $logo->id]);

        $this->assertInstanceOf(Picture::class, $experience->logo);
    }
}
