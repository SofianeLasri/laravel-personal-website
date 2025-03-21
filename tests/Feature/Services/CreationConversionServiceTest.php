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
        $this->service = new CreationConversionService;
    }

    #[Test]
    public function test_convert_draft_to_creation_with_full_data()
    {
        $draft = CreationDraft::factory()
            ->has(CreationDraftFeature::factory()->count(2), 'features')
            ->has(CreationDraftScreenshot::factory()->count(3), 'screenshots')
            ->has(Technology::factory()->count(2), 'technologies')
            ->has(Person::factory()->count(1), 'people')
            ->has(Tag::factory()->count(1), 'tags')
            ->create();

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
            ->has(CreationDraftScreenshot::factory()->count(4), 'screenshots')
            ->has(Technology::factory()->count(3), 'technologies')
            ->has(Person::factory()->count(2), 'people')
            ->has(Tag::factory()->count(2), 'tags')
            ->create();

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
            ->has(CreationDraftScreenshot::factory()->count(1), 'screenshots')
            ->create();

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
}
