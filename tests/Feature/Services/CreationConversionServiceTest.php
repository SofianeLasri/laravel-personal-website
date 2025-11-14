<?php

namespace Tests\Feature\Services;

use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Person;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TranslationKey;
use App\Services\BlogContentDuplicationService;
use App\Services\CreationConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationConversionService::class)]
class CreationConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CreationConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $contentDuplicationService = new BlogContentDuplicationService;
        $this->service = new CreationConversionService($contentDuplicationService);
    }

    #[Test]
    public function test_convert_draft_to_creation_with_full_data()
    {
        $draft = CreationDraft::factory()
            ->has(CreationDraftFeature::factory()->count(2), 'features')
            ->has(Technology::factory()->count(2), 'technologies')
            ->has(Person::factory()->count(1), 'people')
            ->has(Tag::factory()->count(1), 'tags')
            ->create();

        // Add screenshots with proper sequence
        CreationDraftScreenshot::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create(['creation_draft_id' => $draft->id]);

        $creation = $this->service->convertDraftToCreation($draft);

        $this->assertEquals($draft->name, $creation->name);
        $this->assertEquals($draft->slug, $creation->slug);

        $this->assertCount(2, $creation->technologies);
        $this->assertCount(1, $creation->people);
        $this->assertCount(1, $creation->tags);

        $this->assertCount(2, $creation->features);
        $this->assertCount(3, $creation->screenshots);

        $this->assertEquals(
            $draft->short_description_translation_key_id,
            $creation->short_description_translation_key_id
        );
    }

    #[Test]
    public function test_convert_draft_throws_validation_exception_for_missing_translation_keys()
    {
        $this->expectException(ValidationException::class);

        $draft = CreationDraft::factory()->create([
            'short_description_translation_key_id' => null,
            'full_description_translation_key_id' => null,
        ]);

        $this->service->convertDraftToCreation($draft);
    }

    #[Test]
    public function test_update_creation_from_draft_with_full_data()
    {
        $originalCreation = Creation::factory()
            ->withFeatures(2)
            ->withScreenshots(2)
            ->create();

        $draft = CreationDraft::factory()
            ->has(CreationDraftFeature::factory()->count(3), 'features')
            ->has(Technology::factory()->count(3), 'technologies')
            ->has(Person::factory()->count(2), 'people')
            ->has(Tag::factory()->count(2), 'tags')
            ->create();

        // Add screenshots with proper sequence
        CreationDraftScreenshot::factory()
            ->count(4)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create(['creation_draft_id' => $draft->id]);

        $updatedCreation = $this->service->updateCreationFromDraft($draft, $originalCreation);

        $this->assertEquals($draft->name, $updatedCreation->name);
        $this->assertEquals($draft->external_url, $updatedCreation->external_url);

        $this->assertCount(3, $updatedCreation->technologies);
        $this->assertCount(2, $updatedCreation->people);
        $this->assertCount(2, $updatedCreation->tags);

        $this->assertCount(3, $updatedCreation->features);
        $this->assertCount(4, $updatedCreation->screenshots);
    }

    #[Test]
    public function test_update_creation_removes_old_features_and_screenshots()
    {
        $originalCreation = Creation::factory()
            ->withFeatures(3)
            ->withScreenshots(2)
            ->create();

        $draft = CreationDraft::factory()
            ->has(CreationDraftFeature::factory()->count(1), 'features')
            ->create();

        // Add screenshot with proper sequence
        CreationDraftScreenshot::factory()
            ->create([
                'creation_draft_id' => $draft->id,
                'order' => 1,
            ]);

        $this->service->updateCreationFromDraft($draft, $originalCreation);

        $this->assertCount(1, $originalCreation->fresh()->features);
        $this->assertCount(1, $originalCreation->fresh()->screenshots);
    }

    #[Test]
    public function test_convert_draft_with_optional_fields()
    {
        $draft = CreationDraft::factory()->create([
            'ended_at' => null,
            'external_url' => null,
            'source_code_url' => null,
        ]);

        $creation = $this->service->convertDraftToCreation($draft);

        $this->assertNull($creation->ended_at);
        $this->assertNull($creation->external_url);
        $this->assertNull($creation->source_code_url);
    }

    #[Test]
    public function test_sync_relationships_with_empty_values()
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();

        $this->service->syncRelationships($draft, $creation);

        $this->assertCount(0, $creation->technologies);
        $this->assertCount(0, $creation->people);
        $this->assertCount(0, $creation->tags);
    }

    #[Test]
    public function test_feature_creation_with_translation_keys()
    {
        $translationKey = TranslationKey::factory()->create();
        $draftFeature = CreationDraftFeature::factory()->create([
            'title_translation_key_id' => $translationKey->id,
        ]);

        $draft = CreationDraft::factory()->create();
        $draft->features()->save($draftFeature);

        $creation = $this->service->convertDraftToCreation($draft);

        $feature = $creation->features->first();
        $this->assertEquals(
            $draftFeature->title_translation_key_id,
            $feature->title_translation_key_id
        );
    }

    #[Test]
    public function test_screenshot_creation_with_optional_caption()
    {
        $draftScreenshot = CreationDraftScreenshot::factory()->create([
            'caption_translation_key_id' => null,
        ]);

        $draft = CreationDraft::factory()->create();
        $draft->screenshots()->save($draftScreenshot);

        $creation = $this->service->convertDraftToCreation($draft);

        $screenshot = $creation->screenshots->first();
        $this->assertNull($screenshot->caption_translation_key_id);
    }

    #[Test]
    public function test_convert_draft_to_existing_creation()
    {
        $originalCreation = Creation::factory()->create();
        $draft = CreationDraft::fromCreation($originalCreation);

        $draft->name = 'Updated Name';
        $draft->slug = 'updated-slug';

        $newTag = Tag::factory()->create();
        $draft->tags()->attach([$newTag->id]);

        $newTechnologies = Technology::factory()->count(3)->create();
        $draft->technologies()->sync($newTechnologies);

        $this->service->convertDraftToCreation($draft);

        $updatedCreation = Creation::find($originalCreation->id);

        $this->assertEquals($draft->name, $updatedCreation->name);
        $this->assertEquals($draft->slug, $updatedCreation->slug);

        $this->assertCount($draft->tags()->count(), $updatedCreation->tags);
        $this->assertCount($draft->technologies()->count(), $updatedCreation->technologies);
        $this->assertCount($draft->people()->count(), $updatedCreation->people);
        $this->assertCount($draft->features()->count(), $updatedCreation->features);

        foreach ($updatedCreation->tags as $tag) {
            $this->assertTrue($draft->tags->contains($tag));
        }

        foreach ($updatedCreation->technologies as $technology) {
            $this->assertTrue($draft->technologies->contains($technology));
        }
    }

    #[Test]
    public function test_preserves_screenshot_order_on_conversion()
    {
        $draft = CreationDraft::factory()->create();

        // Create screenshots with specific order
        $screenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 1,
        ]);

        $screenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $screenshot3 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 3,
        ]);

        $creation = $this->service->convertDraftToCreation($draft);

        $this->assertCount(3, $creation->screenshots);

        $creationScreenshots = $creation->screenshots->sortBy('order')->values();

        $this->assertEquals($screenshot1->picture_id, $creationScreenshots[0]->picture_id);
        $this->assertEquals(1, $creationScreenshots[0]->order);

        $this->assertEquals($screenshot2->picture_id, $creationScreenshots[1]->picture_id);
        $this->assertEquals(2, $creationScreenshots[1]->order);

        $this->assertEquals($screenshot3->picture_id, $creationScreenshots[2]->picture_id);
        $this->assertEquals(3, $creationScreenshots[2]->order);
    }

    #[Test]
    public function test_recreates_screenshots_with_correct_order_on_update()
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();

        // Create initial screenshots in creation
        $oldScreenshot1 = \App\Models\Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'order' => 1,
        ]);

        $oldScreenshot2 = \App\Models\Screenshot::factory()->create([
            'creation_id' => $creation->id,
            'order' => 2,
        ]);

        // Create new screenshots in draft with different order
        $draftScreenshot1 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 1,
        ]);

        $draftScreenshot2 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $draftScreenshot3 = CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 3,
        ]);

        $updatedCreation = $this->service->updateCreationFromDraft($draft, $creation);

        // Old screenshots should be deleted
        $this->assertDatabaseMissing('screenshots', ['id' => $oldScreenshot1->id]);
        $this->assertDatabaseMissing('screenshots', ['id' => $oldScreenshot2->id]);

        // New screenshots should exist with correct order
        $this->assertCount(3, $updatedCreation->screenshots);

        $creationScreenshots = $updatedCreation->screenshots->sortBy('order')->values();

        $this->assertEquals($draftScreenshot1->picture_id, $creationScreenshots[0]->picture_id);
        $this->assertEquals(1, $creationScreenshots[0]->order);

        $this->assertEquals($draftScreenshot2->picture_id, $creationScreenshots[1]->picture_id);
        $this->assertEquals(2, $creationScreenshots[1]->order);

        $this->assertEquals($draftScreenshot3->picture_id, $creationScreenshots[2]->picture_id);
        $this->assertEquals(3, $creationScreenshots[2]->order);
    }
}
