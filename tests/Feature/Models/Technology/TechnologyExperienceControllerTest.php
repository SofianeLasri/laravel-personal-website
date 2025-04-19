<?php

namespace Tests\Feature\Models\Technology;

use App\Http\Controllers\Admin\Api\TechnologyExperienceController;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(TechnologyExperienceController::class)]
class TechnologyExperienceControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_index()
    {
        $experiences = TechnologyExperience::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.technology-experiences.index'));

        $response->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment([
                'id' => $experiences[0]->id,
                'technology_id' => $experiences[0]->technology_id,
                'description_translation_key_id' => $experiences[0]->description_translation_key_id,
            ]);
    }

    #[Test]
    public function test_store()
    {
        Technology::factory()->create();

        $response = $this->postJson(route('dashboard.api.technology-experiences.store'), [
            'technology_id' => 1,
            'locale' => 'en',
            'description' => 'Experience description',
        ]);

        $response->assertCreated()
            ->assertJsonPath('technology_id', 1);

        $this->assertDatabaseHas('technology_experiences', [
            'technology_id' => 1,
        ]);

        $this->assertDatabaseHas('translations', [
            'text' => 'Experience description',
        ]);
    }

    #[Test]
    public function test_show()
    {
        $experience = TechnologyExperience::factory()->create();

        $response = $this->getJson(route('dashboard.api.technology-experiences.show', $experience));

        $response->assertOk()
            ->assertJsonPath('id', $experience->id)
            ->assertJsonPath('technology_id', $experience->technology_id)
            ->assertJsonPath('description_translation_key_id', $experience->description_translation_key_id);
    }

    #[Test]
    public function test_update()
    {
        $technology = Technology::factory()->create();
        $experience = TechnologyExperience::factory()->create();

        $response = $this->putJson(route('dashboard.api.technology-experiences.update', $experience), [
            'locale' => 'en',
            'description' => 'Updated description',
            'technology_id' => $technology->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('technology_id', $technology->id);

        $this->assertDatabaseHas('technology_experiences', [
            'id' => $experience->id,
            'description_translation_key_id' => $experience->description_translation_key_id,
            'technology_id' => $technology->id,
        ]);

        $this->assertDatabaseHas('translations', [
            'text' => 'Updated description',
        ]);
    }

    #[Test]
    public function test_destroy()
    {
        $experience = TechnologyExperience::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.technology-experiences.destroy', $experience));

        $response->assertNoContent();

        $this->assertDatabaseMissing('technology_experiences', [
            'id' => $experience->id,
        ]);
    }
}
