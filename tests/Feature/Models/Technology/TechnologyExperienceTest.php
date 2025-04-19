<?php

namespace Tests\Feature\Models\Technology;

use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TechnologyExperience::class)]
class TechnologyExperienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_technology_experience()
    {
        $technology = Technology::factory()->create();
        $descriptionKey = TranslationKey::factory()->withTranslations()->create();

        $technologyExperience = TechnologyExperience::factory()->create([
            'technology_id' => $technology->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);

        $this->assertDatabaseHas('technology_experiences', [
            'id' => $technologyExperience->id,
            'technology_id' => $technology->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);
    }

    #[Test]
    public function it_can_have_a_technology()
    {
        $technology = Technology::factory()->create();
        $descriptionKey = TranslationKey::factory()->withTranslations()->create();

        $technologyExperience = TechnologyExperience::factory()->create([
            'technology_id' => $technology->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);

        $this->assertInstanceOf(Technology::class, $technologyExperience->technology);
        $this->assertEquals($technology->id, $technologyExperience->technology->id);
    }

    #[Test]
    public function it_can_have__a_description_translation_key()
    {
        $technology = Technology::factory()->create();
        $descriptionKey = TranslationKey::factory()->withTranslations()->create();

        $technologyExperience = TechnologyExperience::factory()->create([
            'technology_id' => $technology->id,
            'description_translation_key_id' => $descriptionKey->id,
        ]);

        $this->assertInstanceOf(TranslationKey::class, $technologyExperience->descriptionTranslationKey);
        $this->assertEquals($descriptionKey->id, $technologyExperience->descriptionTranslationKey->id);
    }
}
