<?php

namespace Tests\Feature\Models\Creation;

use App\Enums\CreationType;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Creation::class)]
class CreationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_creation()
    {
        $shortDescKey = TranslationKey::factory()->create();
        $fullDescKey = TranslationKey::factory()->create();

        $creation = Creation::factory()->create([
            'name' => 'My Test Project',
            'slug' => 'my-test-project',
            'type' => CreationType::WEBSITE,
            'started_at' => '2023-01-01',
            'ended_at' => '2023-12-31',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'external_url' => 'https://example.com',
            'source_code_url' => 'https://github.com/example/project',
            'featured' => true,
        ]);

        $this->assertDatabaseHas('creations', [
            'id' => $creation->id,
            'name' => 'My Test Project',
            'slug' => 'my-test-project',
            'type' => 'website',
            'featured' => 1,
        ]);

        $this->assertInstanceOf(CreationType::class, $creation->type);
        $this->assertEquals(CreationType::WEBSITE, $creation->type);
    }

    #[Test]
    public function it_can_have_a_logo_and_cover_image()
    {
        $logo = Picture::factory()->create();
        $cover = Picture::factory()->create();

        $creation = Creation::factory()->create([
            'logo_id' => $logo->id,
            'cover_image_id' => $cover->id,
        ]);

        $this->assertInstanceOf(Picture::class, $creation->logo);
        $this->assertInstanceOf(Picture::class, $creation->coverImage);
        $this->assertEquals($logo->id, $creation->logo->id);
        $this->assertEquals($cover->id, $creation->coverImage->id);
    }

    #[Test]
    public function it_can_have_features()
    {
        $creation = Creation::factory()->create();
        Feature::factory()->count(3)->create([
            'creation_id' => $creation->id,
        ]);

        $this->assertCount(3, $creation->features);
        $this->assertInstanceOf(Feature::class, $creation->features->first());
    }

    #[Test]
    public function it_can_have_screenshots()
    {
        $creation = Creation::factory()->create();
        Screenshot::factory()->count(4)->create([
            'creation_id' => $creation->id,
        ]);

        $this->assertCount(4, $creation->screenshots);
        $this->assertInstanceOf(Screenshot::class, $creation->screenshots->first());
    }

    #[Test]
    public function it_can_have_technologies()
    {
        $creation = Creation::factory()->create();
        $technologies = Technology::factory()->count(5)->create();

        $creation->technologies()->attach($technologies);

        $this->assertCount(5, $creation->technologies);
        $this->assertInstanceOf(Technology::class, $creation->technologies->first());
    }

    #[Test]
    public function it_can_have_people()
    {
        $creation = Creation::factory()->create();
        $people = Person::factory()->count(2)->create();

        $creation->people()->attach($people);

        $this->assertCount(2, $creation->people);
        $this->assertInstanceOf(Person::class, $creation->people->first());
    }

    #[Test]
    public function it_can_have_tags()
    {
        $creation = Creation::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $creation->tags()->attach($tags);

        $this->assertCount(3, $creation->tags);
        $this->assertInstanceOf(Tag::class, $creation->tags->first());
    }

    #[Test]
    public function it_can_have_drafts()
    {
        $creation = Creation::factory()->create();
        CreationDraft::factory()->count(2)->create([
            'original_creation_id' => $creation->id,
        ]);

        $this->assertCount(2, $creation->drafts);
        $this->assertInstanceOf(CreationDraft::class, $creation->drafts->first());
    }
}
