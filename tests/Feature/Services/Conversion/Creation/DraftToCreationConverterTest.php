<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\Creation;

use App\Enums\CreationType;
use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Technology;
use App\Services\Conversion\Creation\DraftToCreationConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DraftToCreationConverter::class)]
class DraftToCreationConverterTest extends TestCase
{
    use RefreshDatabase;

    private DraftToCreationConverter $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DraftToCreationConverter::class);
    }

    #[Test]
    public function it_converts_new_draft_to_creation(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertInstanceOf(Creation::class, $creation);
        $this->assertEquals($draft->name, $creation->name);
        $this->assertEquals($draft->slug, $creation->slug);
    }

    #[Test]
    public function it_preserves_draft_type(): void
    {
        $draft = CreationDraft::factory()->create([
            'type' => CreationType::GAME,
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals(CreationType::GAME, $creation->type);
    }

    #[Test]
    public function it_preserves_logo_and_cover_image(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals($draft->logo_id, $creation->logo_id);
        $this->assertEquals($draft->cover_image_id, $creation->cover_image_id);
    }

    #[Test]
    public function it_preserves_translation_keys(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals($draft->short_description_translation_key_id, $creation->short_description_translation_key_id);
        $this->assertEquals($draft->full_description_translation_key_id, $creation->full_description_translation_key_id);
    }

    #[Test]
    public function it_preserves_dates(): void
    {
        $draft = CreationDraft::factory()->create([
            'started_at' => '2020-01-01',
            'ended_at' => '2021-06-15',
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals('2020-01-01', $creation->started_at->format('Y-m-d'));
        $this->assertEquals('2021-06-15', $creation->ended_at->format('Y-m-d'));
    }

    #[Test]
    public function it_preserves_urls(): void
    {
        $draft = CreationDraft::factory()->create([
            'external_url' => 'https://example.com',
            'source_code_url' => 'https://github.com/example/repo',
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals('https://example.com', $creation->external_url);
        $this->assertEquals('https://github.com/example/repo', $creation->source_code_url);
    }

    #[Test]
    public function it_preserves_featured_flag(): void
    {
        $draft = CreationDraft::factory()->create([
            'featured' => true,
            'original_creation_id' => null,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertTrue($creation->featured);
    }

    #[Test]
    public function it_updates_existing_creation(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Old Name',
        ]);
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => $creation->id,
            'name' => 'New Name',
        ]);

        $updatedCreation = $this->service->convert($draft);

        $this->assertEquals($creation->id, $updatedCreation->id);
        $this->assertEquals('New Name', $updatedCreation->name);
    }

    #[Test]
    public function it_syncs_relationships_for_new_creation(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);
        $technologies = Technology::factory()->count(2)->create();
        $draft->technologies()->attach($technologies);

        $creation = $this->service->convert($draft);

        $this->assertEquals(2, $creation->technologies()->count());
    }

    #[Test]
    public function it_syncs_relationships_for_existing_creation(): void
    {
        $creation = Creation::factory()->create();
        $oldTech = Technology::factory()->create();
        $creation->technologies()->attach($oldTech);
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => $creation->id,
        ]);
        $newTech = Technology::factory()->count(3)->create();
        $draft->technologies()->attach($newTech);

        $updatedCreation = $this->service->convert($draft);

        $this->assertEquals(3, $updatedCreation->technologies()->count());
        $this->assertFalse($updatedCreation->technologies->contains($oldTech));
    }

    #[Test]
    public function it_creates_features_for_new_creation(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);
        CreationDraftFeature::factory()->count(2)->create([
            'creation_draft_id' => $draft->id,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals(2, $creation->features()->count());
    }

    #[Test]
    public function it_creates_screenshots_for_new_creation(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);
        CreationDraftScreenshot::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create([
                'creation_draft_id' => $draft->id,
            ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals(3, $creation->screenshots()->count());
    }

    #[Test]
    public function it_creates_contents_for_new_creation(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);
        $markdown = ContentMarkdown::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $creation = $this->service->convert($draft);

        $this->assertEquals(1, $creation->contents()->count());
    }

    #[Test]
    public function it_throws_validation_exception_for_incomplete_draft(): void
    {
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => null,
        ]);
        $draft->logo_id = null;

        $this->expectException(ValidationException::class);

        $this->service->convert($draft);
    }

    #[Test]
    public function it_updates_using_update_method(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Old Name',
        ]);
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => $creation->id,
            'name' => 'Updated Name',
        ]);

        $updatedCreation = $this->service->update($draft, $creation);

        $this->assertEquals($creation->id, $updatedCreation->id);
        $this->assertEquals('Updated Name', $updatedCreation->name);
    }

    #[Test]
    public function it_recreates_features_on_update(): void
    {
        $creation = Creation::factory()->create();
        $creation->features()->create([
            'title_translation_key_id' => 1,
            'description_translation_key_id' => 1,
        ]);
        $draft = CreationDraft::factory()->create([
            'original_creation_id' => $creation->id,
        ]);
        CreationDraftFeature::factory()->count(3)->create([
            'creation_draft_id' => $draft->id,
        ]);

        $updatedCreation = $this->service->update($draft, $creation);

        $this->assertEquals(3, $updatedCreation->features()->count());
    }
}
