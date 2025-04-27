<?php

namespace Tests\Feature\Models\Experience;

use App\Enums\ExperienceType;
use App\Http\Controllers\Admin\Api\ExperienceController;
use App\Models\Experience;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ExperienceController::class)]
class ExperienceControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    #[Test]
    public function test_index()
    {
        Experience::factory()->count(5)->create();

        $response = $this->getJson(route('dashboard.api.experiences.index'));

        $response->assertOk()
            ->assertJsonCount(5);
    }

    #[Test]
    public function test_create()
    {
        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Career Title',
            'organization_name' => 'Organization Name',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Location',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->postJson(route('dashboard.api.experiences.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('organization_name', 'Organization Name')
            ->assertJsonPath('logo_id', $logo->id)
            ->assertJsonPath('type', 'emploi')
            ->assertJsonPath('location', 'Location')
            ->assertJsonPath('website_url', 'https://example.com');

        $this->assertDatabaseHas('experiences', [
            'organization_name' => 'Organization Name',
            'logo_id' => $logo->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'Location',
            'website_url' => 'https://example.com',
        ]);

        $this->assertDatabaseHas('translations', [
            'text' => 'Career Title',
        ]);

        $this->assertDatabaseHas('translations', [
            'text' => 'Short description',
        ]);

        $this->assertDatabaseHas('translations', [
            'text' => 'Full description',
        ]);
    }

    #[Test]
    public function test_show()
    {
        $experience = Experience::factory()->create();

        $response = $this->getJson(route('dashboard.api.experiences.show', $experience));

        $response->assertOk()
            ->assertJsonPath('organization_name', $experience->organization_name)
            ->assertJsonPath('logo_id', $experience->logo_id)
            ->assertJsonPath('type', $experience->type->value)
            ->assertJsonPath('location', $experience->location)
            ->assertJsonPath('website_url', $experience->website_url);
    }

    #[Test]
    public function test_update()
    {
        $experience = Experience::factory()->create();
        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Updated Career Title',
            'organization_name' => 'Updated Organization Name',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Updated Location',
            'website_url' => 'https://updated-example.com',
            'short_description' => 'Updated short description',
            'full_description' => 'Updated full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->putJson(route('dashboard.api.experiences.update', $experience), $data);

        $response->assertOk()
            ->assertJsonPath('organization_name', 'Updated Organization Name')
            ->assertJsonPath('logo_id', $logo->id)
            ->assertJsonPath('type', 'emploi')
            ->assertJsonPath('location', 'Updated Location')
            ->assertJsonPath('website_url', 'https://updated-example.com');

        $this->assertDatabaseHas('experiences', [
            'organization_name' => 'Updated Organization Name',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Updated Location',
            'website_url' => 'https://updated-example.com',
        ]);
    }

    #[Test]
    public function test_destroy()
    {
        $experience = Experience::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.experiences.destroy', $experience));

        $response->assertNoContent();

        $this->assertDatabaseMissing('experiences', [
            'id' => $experience->id,
        ]);
    }
}
