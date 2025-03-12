<?php

namespace Tests\Feature\Models;

use App\Enums\CreationType;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationDraft::class)]
class CreationDraftTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_creation_draft()
    {
        $creationDraft = CreationDraft::factory()->create([
            'name' => 'My Draft Project',
            'slug' => 'my-draft-project',
            'type' => CreationType::WEBSITE,
            'started_at' => '2023-01-01',
            'ended_at' => '2023-12-31',
            'external_url' => 'https://example.com',
            'source_code_url' => 'https://github.com/example/project',
            'featured' => true,
        ]);

        $this->assertDatabaseHas('creation_drafts', [
            'id' => $creationDraft->id,
            'name' => 'My Draft Project',
            'slug' => 'my-draft-project',
            'type' => 'website',
            'featured' => 1,
        ]);

        $this->assertInstanceOf(CreationType::class, $creationDraft->type);
        $this->assertEquals(CreationType::WEBSITE, $creationDraft->type);
    }

    #[Test]
    public function it_can_have_optional_images()
    {
        $creationDraft = CreationDraft::factory()->create([
            'logo_id' => null,
            'cover_image_id' => null,
        ]);

        $this->assertNull($creationDraft->logo);
        $this->assertNull($creationDraft->coverImage);
    }

    #[Test]
    public function it_can_have_original_creation()
    {
        $originalCreation = Creation::factory()->create();
        $creationDraft = CreationDraft::factory()->create([
            'original_creation_id' => $originalCreation->id,
        ]);

        $this->assertInstanceOf(Creation::class, $creationDraft->originalCreation);
        $this->assertEquals($originalCreation->id, $creationDraft->originalCreation->id);
    }

    #[Test]
    public function it_can_have_features()
    {
        $creationDraft = CreationDraft::factory()->create();
        CreationDraftFeature::factory()->count(3)->create([
            'creation_draft_id' => $creationDraft->id,
        ]);

        $this->assertCount(3, $creationDraft->features);
        $this->assertInstanceOf(CreationDraftFeature::class, $creationDraft->features->first());
    }

    #[Test]
    public function it_can_have_screenshots()
    {
        $creationDraft = CreationDraft::factory()->create();
        CreationDraftScreenshot::factory()->count(4)->create([
            'creation_draft_id' => $creationDraft->id,
        ]);

        $this->assertCount(4, $creationDraft->screenshots);
        $this->assertInstanceOf(CreationDraftScreenshot::class, $creationDraft->screenshots->first());
    }

    #[Test]
    public function it_can_have_technologies()
    {
        $creationDraft = CreationDraft::factory()->create();
        $technologies = Technology::factory()->count(5)->create();

        $creationDraft->technologies()->attach($technologies);

        $this->assertCount(5, $creationDraft->technologies);
        $this->assertInstanceOf(Technology::class, $creationDraft->technologies->first());
    }

    #[Test]
    public function it_can_have_people()
    {
        $creationDraft = CreationDraft::factory()->create();
        $people = Person::factory()->count(2)->create();

        $creationDraft->people()->attach($people);

        $this->assertCount(2, $creationDraft->people);
        $this->assertInstanceOf(Person::class, $creationDraft->people->first());
    }

    #[Test]
    public function it_can_have_tags()
    {
        $creationDraft = CreationDraft::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $creationDraft->tags()->attach($tags);

        $this->assertCount(3, $creationDraft->tags);
        $this->assertInstanceOf(Tag::class, $creationDraft->tags->first());
    }

    #[Test]
    public function it_can_have_short_description()
    {
        $shortDescKey = TranslationKey::factory()->create(['key' => 'draft.test.short']);
        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Description courte du brouillon',
        ]);

        $creationDraft = CreationDraft::factory()->create([
            'short_description_translation_key_id' => $shortDescKey->id,
        ]);

        $this->assertEquals('Description courte du brouillon', $creationDraft->getShortDescription('fr'));
    }

    #[Test]
    public function it_can_have_full_description()
    {
        $fullDescKey = TranslationKey::factory()->create(['key' => 'draft.test.full']);
        Translation::create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète du brouillon',
        ]);

        $creationDraft = CreationDraft::factory()->create([
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $this->assertEquals('Description complète du brouillon', $creationDraft->getFullDescription('fr'));
    }

    #[Test]
    public function get_short_descriptions_returns_empty_string_if_not_relation()
    {
        $creationDraft = CreationDraft::factory()->create([
            'short_description_translation_key_id' => null,
        ]);

        $this->assertEquals('', $creationDraft->getShortDescription('fr'));
    }

    #[Test]
    public function get_full_descriptions_returns_empty_string_if_not_relation()
    {
        $creationDraft = CreationDraft::factory()->create([
            'full_description_translation_key_id' => null,
        ]);

        $this->assertEquals('', $creationDraft->getFullDescription('fr'));
    }

    #[Test]
    public function it_can_create_a_draft_from_an_existing_creation()
    {
        $creation = $this->createFullCreation();

        $draft = CreationDraft::fromCreation($creation);

        $this->assertEquals($creation->name, $draft->name);
        $this->assertEquals($creation->slug, $draft->slug);
        $this->assertEquals($creation->type, $draft->type);
        $this->assertEquals($creation->started_at->format('Y-m-d'), $draft->started_at->format('Y-m-d'));
        $this->assertEquals($creation->external_url, $draft->external_url);
        $this->assertEquals($creation->featured, $draft->featured);
        $this->assertEquals($creation->id, $draft->original_creation_id);

        $this->assertCount(2, $draft->features);
        $this->assertCount(2, $draft->screenshots);
        $this->assertCount(3, $draft->technologies);
        $this->assertCount(2, $draft->people);
        $this->assertCount(2, $draft->tags);
    }

    #[Test]
    public function it_can_create_a_creation_from_a_draft()
    {
        $draft = $this->createFullDraft();

        $creation = $draft->toCreation();

        $this->assertEquals($draft->name, $creation->name);
        $this->assertEquals($draft->slug, $creation->slug);
        $this->assertEquals($draft->type, $creation->type);
        $this->assertEquals($draft->started_at->format('Y-m-d'), $creation->started_at->format('Y-m-d'));
        $this->assertEquals($draft->external_url, $creation->external_url);
        $this->assertEquals($draft->featured, $creation->featured);

        $this->assertCount(2, $creation->features);
        $this->assertCount(2, $creation->screenshots);
        $this->assertCount(3, $creation->technologies);
        $this->assertCount(2, $creation->people);
        $this->assertCount(2, $creation->tags);
    }

    #[Test]
    public function it_cannot_create_creation_without_descriptions()
    {
        $draft = CreationDraft::factory()->create([
            'short_description_translation_key_id' => null,
            'full_description_translation_key_id' => null,
        ]);

        $this->expectException(Exception::class);
        $draft->toCreation();
    }

    #[Test]
    public function it_can_update_an_existing_creation_from_a_draft()
    {
        $existingCreation = Creation::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
        ]);

        $draft = CreationDraft::factory()->create([
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'type' => CreationType::GAME,
        ]);

        $updatedCreation = $draft->updateCreation($existingCreation);

        $this->assertEquals('Updated Name', $updatedCreation->name);
        $this->assertEquals('updated-slug', $updatedCreation->slug);
        $this->assertEquals(CreationType::GAME, $updatedCreation->type);
        $this->assertEquals($existingCreation->id, $updatedCreation->id);
    }

    #[Test]
    public function it_can_update_an_existing_creation_from_a_draft_with_relations()
    {
        $existingCreation = $this->createFullCreation();

        $draft = $this->createFullDraft();

        $updatedCreation = $draft->updateCreation($existingCreation);

        $this->assertEquals($draft->name, $updatedCreation->name);
        $this->assertEquals($draft->slug, $updatedCreation->slug);
        $this->assertEquals($draft->type, $updatedCreation->type);
        $this->assertEquals($draft->started_at->format('Y-m-d'), $updatedCreation->started_at->format('Y-m-d'));
        $this->assertEquals($draft->external_url, $updatedCreation->external_url);
        $this->assertEquals($draft->featured, $updatedCreation->featured);

        $this->assertCount(2, $updatedCreation->features);
        $this->assertCount(2, $updatedCreation->screenshots);
        $this->assertCount(3, $updatedCreation->technologies);
        $this->assertCount(2, $updatedCreation->people);
        $this->assertCount(2, $updatedCreation->tags);
    }

    /**
     * Helper pour créer une création complète avec toutes ses relations
     */
    private function createFullCreation(): Creation
    {
        $shortDescKey = TranslationKey::factory()->create(['key' => 'creation.test.short']);
        $fullDescKey = TranslationKey::factory()->create(['key' => 'creation.test.full']);

        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Description courte en français',
        ]);

        Translation::create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète en français',
        ]);

        $creation = Creation::factory()->create([
            'name' => 'Test Complete Creation',
            'slug' => 'test-complete-creation',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $featureTitleKey = TranslationKey::factory()->create(['key' => 'feature.test.title']);
        $featureDescKey = TranslationKey::factory()->create(['key' => 'feature.test.desc']);

        Feature::factory()->count(2)->create([
            'creation_id' => $creation->id,
            'title_translation_key_id' => $featureTitleKey->id,
            'description_translation_key_id' => $featureDescKey->id,
        ]);

        $captionKey = TranslationKey::factory()->create(['key' => 'screenshot.test.caption']);
        Screenshot::factory()->count(2)->create([
            'creation_id' => $creation->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $technologies = Technology::factory()->count(3)->create();
        $people = Person::factory()->count(2)->create();
        $tags = Tag::factory()->count(2)->create();

        $creation->technologies()->attach($technologies);
        $creation->people()->attach($people);
        $creation->tags()->attach($tags);

        return $creation;
    }

    /**
     * Helper pour créer un brouillon complet avec toutes ses relations
     */
    private function createFullDraft(): CreationDraft
    {
        $shortDescKey = TranslationKey::factory()->create(['key' => 'draft.test.short']);
        $fullDescKey = TranslationKey::factory()->create(['key' => 'draft.test.full']);

        Translation::create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Description courte du brouillon',
        ]);

        Translation::create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète du brouillon',
        ]);

        $draft = CreationDraft::factory()->create([
            'name' => 'Test Complete Draft',
            'slug' => 'test-complete-draft',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $featureTitleKey = TranslationKey::factory()->create(['key' => 'feature.draft.title']);
        $featureDescKey = TranslationKey::factory()->create(['key' => 'feature.draft.desc']);

        CreationDraftFeature::factory()->count(2)->create([
            'creation_draft_id' => $draft->id,
            'title_translation_key_id' => $featureTitleKey->id,
            'description_translation_key_id' => $featureDescKey->id,
        ]);

        $captionKey = TranslationKey::factory()->create(['key' => 'screenshot.draft.caption']);
        CreationDraftScreenshot::factory()->count(2)->create([
            'creation_draft_id' => $draft->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $technologies = Technology::factory()->count(3)->create();
        $people = Person::factory()->count(2)->create();
        $tags = Tag::factory()->count(2)->create();

        $draft->technologies()->attach($technologies);
        $draft->people()->attach($people);
        $draft->tags()->attach($tags);

        return $draft;
    }
}
