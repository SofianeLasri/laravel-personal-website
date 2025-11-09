<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Controllers\Admin\Api\CreationController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\User;
use App\Services\CreationConversionService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationController::class)]
class CreationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_index_returns_all_creations()
    {
        Creation::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.creations.index'));

        $response->assertOk()
            ->assertJsonCount(3);
    }

    #[Test]
    public function test_store_creates_creation_from_valid_draft()
    {
        $draft = CreationDraft::factory()
            ->has(CreationDraftFeature::factory()->count(2), 'features')
            ->has(Technology::factory()->count(2), 'technologies')
            ->has(Person::factory()->count(1), 'people')
            ->has(Tag::factory()->count(1), 'tags')
            ->create();

        CreationDraftScreenshot::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create(['creation_draft_id' => $draft->id]);

        $response = $this->postJson(route('dashboard.api.creations.store'), [
            'draft_id' => $draft->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name']);

        $creation = Creation::first();
        $this->assertEquals(2, $creation->features()->count());
        $this->assertEquals(3, $creation->screenshots()->count());
        $this->assertEquals(2, $creation->technologies()->count());
        $this->assertEquals(1, $creation->people()->count());
        $this->assertEquals(1, $creation->tags()->count());
    }

    #[Test]
    public function test_store_returns_validation_error_for_invalid_draft()
    {
        $response = $this->postJson(route('dashboard.api.creations.store'), [
            'draft_id' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['draft_id']);
    }

    #[Test]
    public function test_store_handles_missing_translation_keys()
    {
        $draft = CreationDraft::factory()->create([
            'short_description_translation_key_id' => null,
            'full_description_translation_key_id' => null,
        ]);

        $response = $this->postJson(route('dashboard.api.creations.store'), [
            'draft_id' => $draft->id,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function test_store_handles_unexpected_errors()
    {
        $draft = CreationDraft::factory()->create();

        $this->mock(CreationConversionService::class, function ($mock) use ($draft) {
            $mock->shouldReceive('convertDraftToCreation')
                ->with($draft)
                ->andThrow(new Exception('Something went wrong'));
        });

        $response = $this->postJson(route('dashboard.api.creations.store'), [
            'draft_id' => $draft->id,
        ]);

        $response->assertServerError();
    }

    #[Test]
    public function test_show_returns_specific_creation()
    {
        $creation = Creation::factory()->create();

        $response = $this->getJson(route('dashboard.api.creations.show', $creation));

        $response->assertOk()
            ->assertJsonPath('id', $creation->id);
    }

    #[Test]
    public function test_update_toggles_featured_status()
    {
        $creation = Creation::factory()->create(['featured' => false]);

        $response = $this->putJson(route('dashboard.api.creations.update', $creation), [
            'featured' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('featured', true);

        $this->assertTrue($creation->fresh()->featured);
    }

    #[Test]
    public function test_update_validates_featured_field()
    {
        $creation = Creation::factory()->create();

        $response = $this->putJson(route('dashboard.api.creations.update', $creation), [
            'featured' => 'not-a-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['featured']);
    }

    #[Test]
    public function test_destroy_deletes_creation()
    {
        $creation = Creation::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.creations.destroy', $creation));

        $response->assertNoContent();
        $this->assertDatabaseMissing('creations', ['id' => $creation->id]);
    }

    #[Test]
    public function test_creation_relationships_are_properly_transferred()
    {
        $draft = CreationDraft::factory()
            ->hasFeatures(2)
            ->hasTechnologies(2)
            ->hasPeople(1)
            ->hasTags(1)
            ->create();

        CreationDraftScreenshot::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create(['creation_draft_id' => $draft->id]);

        $this->postJson(route('dashboard.api.creations.store'), ['draft_id' => $draft->id]);

        $creation = Creation::first();

        $this->assertEquals(2, Feature::where('creation_id', $creation->id)->count());
        $this->assertEquals(3, Screenshot::where('creation_id', $creation->id)->count());
        $this->assertEquals(2, $creation->technologies()->count());
        $this->assertEquals(1, $creation->people()->count());
        $this->assertEquals(1, $creation->tags()->count());
    }

    #[Test]
    public function test_creation_date_formats_are_correct()
    {
        $draft = CreationDraft::factory()->create([
            'started_at' => '2025-01-01',
            'ended_at' => '2025-12-31',
        ]);

        $response = $this->postJson(route('dashboard.api.creations.store'), ['draft_id' => $draft->id]);

        $creation = Creation::first();
        $this->assertEquals('2025-01-01', $creation->started_at->toDateString());
        $this->assertEquals('2025-12-31', $creation->ended_at->toDateString());
    }
}
