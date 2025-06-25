<?php

namespace Tests\Feature\Models\Technology;

use App\Http\Controllers\Admin\Api\TechnologyController;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TechnologyController::class)]
class TechnologyControllerTest extends TestCase
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
    public function test_index()
    {
        Technology::factory()->count(5)->create();

        $response = $this->get(route('dashboard.api.technologies.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(5);
    }

    #[Test]
    public function test_store()
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'Test Technology',
            'type' => 'framework',
            'icon_picture_id' => $picture->id,
            'locale' => 'en',
            'description' => 'This is a test technology.',
        ];

        $response = $this->post(route('dashboard.api.technologies.store'), $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('technologies', [
            'name' => 'Test Technology',
            'type' => 'framework',
            'icon_picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function test_show()
    {
        $technology = Technology::factory()->create();

        $response = $this->get(route('dashboard.api.technologies.show', $technology));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $technology->id,
        ]);
    }

    #[Test]
    public function test_update()
    {
        $technology = Technology::factory()->create();
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'Updated Technology',
            'type' => 'library',
            'icon_picture_id' => $picture->id,
            'locale' => 'fr',
            'description' => 'This is an updated technology.',
        ];

        $response = $this->put(route('dashboard.api.technologies.update', $technology), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('technologies', [
            'id' => $technology->id,
            'name' => 'Updated Technology',
            'type' => 'library',
        ]);
    }

    #[Test]
    public function test_destroy()
    {
        $technology = Technology::factory()->create();

        $response = $this->delete(route('dashboard.api.technologies.destroy', $technology));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('technologies', [
            'id' => $technology->id,
        ]);
    }

    #[Test]
    public function test_check_associations()
    {
        $technology = Technology::factory()->create();

        $creation = Creation::factory()->create();
        $creation->technologies()->attach($technology);
        $draft = CreationDraft::factory()->create();
        $draft->technologies()->attach($technology);

        $response = $this->get(route('dashboard.api.technologies.check-associations', $technology));

        $response->assertStatus(200);
        $response->assertJson([
            'has_associations' => true,
            'creations_count' => 1,
            'creation_drafts_count' => 1,
        ]);
    }
}
