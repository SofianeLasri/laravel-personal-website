<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Controllers\Admin\Api\CreationDraftController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationDraftController::class)]
class CreationDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    #[Test]
    public function test_index_returns_all_drafts()
    {
        CreationDraft::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.creation-drafts.index'));

        $response->assertOk()
            ->assertJsonCount(3);
    }

    #[Test]
    public function test_store_creates_new_draft_with_relationships()
    {
        $person = Person::factory()->create();
        $technology = Technology::factory()->create();
        $tag = Tag::factory()->create();
        $picture = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'name' => 'New Project',
            'slug' => 'new-project',
            'type' => 'website',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short desc',
            'full_description_content' => 'Full desc',
            'people' => [$person->id],
            'technologies' => [$technology->id],
            'tags' => [$tag->id],
            'logo_id' => $picture->id,
            'cover_image_id' => $picture->id,
            'external_url' => 'https://example.com',
            'source_code_url' => 'https://github.com',
        ];

        $response = $this->postJson(route('dashboard.api.creation-drafts.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('name', 'New Project');

        $draft = CreationDraft::first();
        $this->assertEquals(1, $draft->people()->count());
        $this->assertEquals(1, $draft->technologies()->count());
        $this->assertEquals(1, $draft->tags()->count());
        $this->assertEquals($picture->id, $draft->logo_id);
    }

    #[Test]
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson(route('dashboard.api.creation-drafts.store'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name', 'slug', 'type',
                'started_at', 'short_description_content',
                'full_description_content', 'locale',
            ]);
    }

    #[Test]
    public function test_store_with_missing_optional_fields()
    {
        $data = [
            'locale' => 'en',
            'name' => 'Minimal Draft',
            'slug' => 'minimal-draft',
            'type' => 'library',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
        ];

        $response = $this->postJson(route('dashboard.api.creation-drafts.store'), $data);

        $response->assertCreated();
        $draft = CreationDraft::first();
        $this->assertNull($draft->ended_at);
        $this->assertNull($draft->external_url);
    }

    #[Test]
    public function test_show_returns_specific_draft()
    {
        $draft = CreationDraft::factory()->create();

        $response = $this->getJson(route('dashboard.api.creation-drafts.show', ['creation_draft' => $draft]));

        $response->assertOk()
            ->assertJsonPath('id', $draft->id);
    }

    #[Test]
    public function test_update_modifies_existing_draft()
    {
        $draft = CreationDraft::factory()->create();
        $newPerson = Person::factory()->create();

        $data = [
            'locale' => 'fr',
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'type' => 'game',
            'started_at' => '2025-02-01',
            'short_description_content' => 'Nouvelle description courte',
            'full_description_content' => 'Nouvelle description longue',
            'people' => [$newPerson->id],
        ];

        $response = $this->putJson(route('dashboard.api.creation-drafts.show', ['creation_draft' => $draft]), $data);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Name');

        $draft->refresh();
        $translation = Translation::where('translation_key_id', $draft->short_description_translation_key_id)
            ->where('locale', 'fr')
            ->first();

        $this->assertEquals('Nouvelle description courte', $translation->text);
        $this->assertEquals(1, $draft->people()->count());
    }

    #[Test]
    public function test_update_modifies_existing_relations()
    {
        $draft = CreationDraft::factory()
            ->hasPeople(2)
            ->hasTechnologies(2)
            ->hasTags(2)
            ->create();

        $newPerson = Person::factory()->create();
        $newTechnology = Technology::factory()->create();
        $newTag = Tag::factory()->create();

        $data = [
            'locale' => 'en',
            'name' => 'Updated Relations',
            'slug' => 'updated-relations',
            'type' => 'portfolio',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
            'people' => [$newPerson->id],
            'technologies' => [$newTechnology->id],
            'tags' => [$newTag->id],
        ];

        $response = $this->putJson(route('dashboard.api.creation-drafts.show', ['creation_draft' => $draft]), $data);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Relations');

        $draft->refresh();
        $this->assertEquals(1, $draft->people()->count());
        $this->assertEquals(1, $draft->technologies()->count());
        $this->assertEquals(1, $draft->tags()->count());
    }

    #[Test]
    public function test_destroy_deletes_draft_and_relations()
    {
        $draft = CreationDraft::factory()
            ->hasFeatures(2)
            ->hasScreenshots(3)
            ->create();

        $response = $this->deleteJson(route('dashboard.api.creation-drafts.show', ['creation_draft' => $draft]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('creation_drafts', ['id' => $draft->id]);
        $this->assertEquals(0, CreationDraftFeature::count());
        $this->assertEquals(0, CreationDraftScreenshot::count());
    }

    #[Test]
    public function test_handles_translation_reuse_on_update()
    {
        $draft = CreationDraft::factory()->create();
        $originalKey = $draft->short_description_translation_key_id;

        $data = [
            'locale' => 'en',
            'name' => 'Same Translation Key',
            'slug' => 'same-key',
            'type' => 'tool',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Updated text',
            'full_description_content' => 'Updated full text',
        ];

        $this->putJson(route('dashboard.api.creation-drafts.show', ['creation_draft' => $draft]), $data);

        $draft->refresh();
        $this->assertEquals($originalKey, $draft->short_description_translation_key_id);
    }

    #[Test]
    public function test_handles_optional_fields()
    {
        $data = [
            'locale' => 'en',
            'name' => 'Minimal Draft',
            'slug' => 'minimal-draft',
            'type' => 'library',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
        ];

        $response = $this->postJson(route('dashboard.api.creation-drafts.index'), $data);

        $response->assertCreated();
        $draft = CreationDraft::first();
        $this->assertNull($draft->ended_at);
        $this->assertNull($draft->external_url);
    }

    #[Test]
    public function test_validates_relationship_existence()
    {
        $data = [
            'locale' => 'en',
            'name' => 'Invalid Relations',
            'slug' => 'invalid-relations',
            'type' => 'portfolio',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
            'people' => [999],
            'technologies' => [999],
            'tags' => [999],
        ];

        $response = $this->postJson(route('dashboard.api.creation-drafts.index'), $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['people.0', 'technologies.0', 'tags.0']);
    }

    public function test_handles_original_creation_id()
    {
        $creation = Creation::factory()->create();

        $data = [
            'locale' => 'en',
            'name' => 'Derived Draft',
            'slug' => 'derived-draft',
            'type' => 'portfolio',
            'started_at' => '2025-01-01',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
            'original_creation_id' => $creation->id,
        ];

        $response = $this->postJson(route('dashboard.api.creation-drafts.index'), $data);

        $response->assertCreated();
        $this->assertEquals(
            $creation->id,
            CreationDraft::first()->original_creation_id
        );
    }

    #[Test]
    public function test_handles_date_formatting()
    {
        $data = [
            'locale' => 'en',
            'name' => 'Date Test',
            'slug' => 'date-test',
            'type' => 'website',
            'started_at' => '2025-03-20',
            'ended_at' => '2025-12-31',
            'short_description_content' => 'Short',
            'full_description_content' => 'Full',
        ];

        $this->postJson(route('dashboard.api.creation-drafts.index'), $data);

        $draft = CreationDraft::first();
        $this->assertEquals(
            '2025-03-20',
            $draft->started_at->toDateString()
        );
        $this->assertEquals(
            '2025-12-31',
            $draft->ended_at->toDateString()
        );
    }

    #[Test]
    public function test_attach_person()
    {
        $draft = CreationDraft::factory()->create();
        $person = Person::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-drafts.attach-person', ['creation_draft' => $draft]), [
            'person_id' => $person->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('person.id', $person->id);

        $this->assertDatabaseHas('creation_draft_person', [
            'creation_draft_id' => $draft->id,
            'person_id' => $person->id,
        ]);
    }

    #[Test]
    public function test_detach_person()
    {
        $draft = CreationDraft::factory()->create();
        $person = Person::factory()->create();
        $draft->people()->attach($person);

        $response = $this->postJson(route('dashboard.api.creation-drafts.detach-person', ['creation_draft' => $draft]), [
            'person_id' => $person->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('creation_draft_person', [
            'creation_draft_id' => $draft->id,
            'person_id' => $person->id,
        ]);
    }

    #[Test]
    public function test_get_people()
    {
        $draft = CreationDraft::factory()->create();
        $people = Person::factory()->count(3)->create();
        $draft->people()->attach($people);

        $response = $this->getJson(route('dashboard.api.creation-drafts.people', ['creation_draft' => $draft]));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $people[0]->id);
    }

    #[Test]
    public function test_attach_tag()
    {
        $draft = CreationDraft::factory()->create();
        $tag = Tag::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-drafts.attach-tag', ['creation_draft' => $draft]), [
            'tag_id' => $tag->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('tag.id', $tag->id);

        $this->assertDatabaseHas('creation_draft_tag', [
            'creation_draft_id' => $draft->id,
            'tag_id' => $tag->id,
        ]);
    }

    #[Test]
    public function test_detach_tag()
    {
        $draft = CreationDraft::factory()->create();
        $tag = Tag::factory()->create();
        $draft->tags()->attach($tag);

        $response = $this->postJson(route('dashboard.api.creation-drafts.detach-tag', ['creation_draft' => $draft]), [
            'tag_id' => $tag->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('creation_draft_tag', [
            'creation_draft_id' => $draft->id,
            'tag_id' => $tag->id,
        ]);
    }

    #[Test]
    public function test_get_tags()
    {
        $draft = CreationDraft::factory()->create();
        $tags = Tag::factory()->count(3)->create();
        $draft->tags()->attach($tags);

        $response = $this->getJson(route('dashboard.api.creation-drafts.tags', ['creation_draft' => $draft]));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $tags[0]->id);
    }

    #[Test]
    public function test_attach_technology()
    {
        $draft = CreationDraft::factory()->create();
        $technology = Technology::factory()->create();

        $response = $this->postJson(route('dashboard.api.creation-drafts.attach-technology', ['creation_draft' => $draft]), [
            'technology_id' => $technology->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('technology.id', $technology->id);

        $this->assertDatabaseHas('creation_draft_technology', [
            'creation_draft_id' => $draft->id,
            'technology_id' => $technology->id,
        ]);
    }

    #[Test]
    public function test_detach_technology()
    {
        $draft = CreationDraft::factory()->create();
        $technology = Technology::factory()->create();
        $draft->technologies()->attach($technology);

        $response = $this->postJson(route('dashboard.api.creation-drafts.detach-technology', ['creation_draft' => $draft]), [
            'technology_id' => $technology->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('creation_draft_technology', [
            'creation_draft_id' => $draft->id,
            'technology_id' => $technology->id,
        ]);
    }

    #[Test]
    public function test_get_technologies()
    {
        $draft = CreationDraft::factory()->create();
        $technologies = Technology::factory()->count(3)->create();
        $draft->technologies()->attach($technologies);

        $response = $this->getJson(route('dashboard.api.creation-drafts.technologies', ['creation_draft' => $draft]));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', $technologies[0]->id);
    }
}
