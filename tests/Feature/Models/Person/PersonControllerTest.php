<?php

namespace Tests\Feature\Models\Person;

use App\Http\Controllers\Admin\Api\PersonController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Person;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PersonController::class)]
class PersonControllerTest extends TestCase
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
    public function test_index_returns_all_persons()
    {
        Person::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.people.index'));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonStructure([
                ['id', 'name', 'picture_id', 'created_at', 'updated_at'],
            ]);
    }

    #[Test]
    public function test_store_creates_new_person_with_valid_data()
    {
        $picture = Picture::factory()->create();

        $response = $this->postJson(route('dashboard.api.people.store'), [
            'name' => 'John Doe',
            'picture_id' => $picture->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'John Doe');

        $this->assertDatabaseHas('people', [
            'name' => 'John Doe',
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson(route('dashboard.api.people.store'), []);

        $response->assertUnprocessable();
    }

    #[Test]
    public function test_store_validates_picture_existence()
    {
        $response = $this->postJson(route('dashboard.api.people.store'), [
            'name' => 'Invalid',
            'picture_id' => 999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['picture_id']);
    }

    #[Test]
    public function test_show_returns_specific_person()
    {
        $person = Person::factory()->create();

        $response = $this->getJson(route('dashboard.api.people.show', $person));

        $response->assertOk()
            ->assertJsonPath('id', $person->id)
            ->assertJsonPath('name', $person->name);
    }

    #[Test]
    public function test_show_returns_404_for_non_existing_person()
    {
        $response = $this->getJson(route('dashboard.api.people.show', 999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_modifies_existing_person()
    {
        $person = Person::factory()->create();
        $newPicture = Picture::factory()->create();

        $response = $this->putJson(route('dashboard.api.people.update', $person), [
            'name' => 'Updated Name',
            'picture_id' => $newPicture->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('picture_id', $newPicture->id);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function test_update_validates_partial_data()
    {
        $person = Person::factory()->create();

        $response = $this->putJson(route('dashboard.api.people.update', $person), [
            'picture_id' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picture_id']);
    }

    #[Test]
    public function test_destroy_deletes_person()
    {
        $person = Person::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.people.destroy', $person));

        $response->assertNoContent();
        $this->assertDatabaseMissing('people', ['id' => $person->id]);
    }

    #[Test]
    public function test_destroy_returns_404_for_non_existing_person()
    {
        $response = $this->deleteJson(route('dashboard.api.people.destroy', 999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_check_associations()
    {
        $person = Person::factory()->create();
        $creation = Creation::factory()->create();
        $draft = CreationDraft::factory()->create();

        $person->creations()->attach($creation);
        $person->creationDrafts()->attach($draft);

        $response = $this->getJson(route('dashboard.api.people.check-associations', $person));

        $response->assertOk()
            ->assertJson([
                'has_associations' => true,
                'creations_count' => 1,
                'creation_drafts_count' => 1,
            ]);
    }
}
