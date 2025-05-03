<?php

namespace Tests\Feature\Models\Technology;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Technology::class)]
class TechnologyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_technology()
    {
        $descKey = TranslationKey::factory()->create();

        $technology = Technology::factory()->create([
            'name' => 'Laravel',
            'svg_icon' => '<svg>...</svg>',
            'description_translation_key_id' => $descKey->id,
        ]);

        $this->assertDatabaseHas('technologies', [
            'id' => $technology->id,
            'name' => 'Laravel',
        ]);
    }

    #[Test]
    public function it_can_be_associated_with_creations()
    {
        $technology = Technology::factory()->create();
        $creations = Creation::factory()->count(3)->create();

        $technology->creations()->attach($creations);

        $this->assertCount(3, $technology->creations);
        $this->assertInstanceOf(Creation::class, $technology->creations->first());
    }

    #[Test]
    public function it_can_be_associated_with_creation_drafts()
    {
        $technology = Technology::factory()->create();
        $creationDrafts = CreationDraft::factory()->count(3)->create();

        $technology->creationDrafts()->attach($creationDrafts);

        $this->assertCount(3, $technology->creationDrafts);
        $this->assertInstanceOf(CreationDraft::class, $technology->creationDrafts->first());
    }

    #[Test]
    public function it_can_get_translated_description()
    {
        $descKey = TranslationKey::factory()->create(['key' => 'technology.description.test']);

        Translation::factory()->create([
            'translation_key_id' => $descKey->id,
            'locale' => 'fr',
            'text' => 'Un framework PHP',
        ]);

        Translation::factory()->create([
            'translation_key_id' => $descKey->id,
            'locale' => 'en',
            'text' => 'A PHP framework',
        ]);

        $technology = Technology::factory()->create([
            'description_translation_key_id' => $descKey->id,
        ]);

        $this->assertEquals('Un framework PHP', $technology->getDescription('fr'));
        $this->assertEquals('A PHP framework', $technology->getDescription('en'));
    }

    public function test_scopes()
    {
        Technology::factory()->create(['type' => 'library']);
        $this->assertTrue(Technology::library()->exists());
        $this->assertFalse(Technology::framework()->exists());
        $this->assertFalse(Technology::language()->exists());
        $this->assertFalse(Technology::other()->exists());
    }
}
