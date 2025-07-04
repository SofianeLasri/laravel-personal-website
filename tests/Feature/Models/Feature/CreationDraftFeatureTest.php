<?php

namespace Tests\Feature\Models\Feature;

use App\Models\CreationDraft as Creation;
use App\Models\CreationDraftFeature as Feature;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Feature::class)]
class CreationDraftFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_feature()
    {
        $creation = Creation::factory()->create();
        $titleKey = TranslationKey::factory()->create();
        $descKey = TranslationKey::factory()->create();

        $feature = Feature::factory()->create([
            'creation_draft_id' => $creation->id,
            'title_translation_key_id' => $titleKey->id,
            'description_translation_key_id' => $descKey->id,
        ]);

        $this->assertDatabaseHas('creation_draft_features', [
            'id' => $feature->id,
            'creation_draft_id' => $creation->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_creation()
    {
        $creation = Creation::factory()->create();
        $feature = Feature::factory()->create(['creation_draft_id' => $creation->id]);

        $this->assertInstanceOf(Creation::class, $feature->creationDraft);
        $this->assertEquals($creation->id, $feature->creationDraft->id);
    }

    #[Test]
    public function it_can_have_an_optional_picture()
    {
        $picture = Picture::factory()->create();
        $feature = Feature::factory()->create(['picture_id' => $picture->id]);

        $this->assertInstanceOf(Picture::class, $feature->picture);

        $featureWithoutPicture = Feature::factory()->create(['picture_id' => null]);
        $this->assertNull($featureWithoutPicture->picture);
    }
}
