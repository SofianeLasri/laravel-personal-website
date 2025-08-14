<?php

namespace Tests\Feature\Models\Experience;

use App\Enums\ExperienceType;
use App\Http\Controllers\Admin\Api\ExperienceController;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
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
    public function test_create_with_technologies()
    {
        $technologies = Technology::factory()->count(3)->create();
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
            'technologies' => $technologies->pluck('id')->toArray(),
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

        $this->assertDatabaseHas('experience_technology', [
            'experience_id' => $response->json('id'),
            'technology_id' => $technologies[0]->id,
        ]);

        $this->assertDatabaseHas('experience_technology', [
            'experience_id' => $response->json('id'),
            'technology_id' => $technologies[1]->id,
        ]);

        $this->assertDatabaseHas('experience_technology', [
            'experience_id' => $response->json('id'),
            'technology_id' => $technologies[2]->id,
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
    public function test_update_with_no_technologies()
    {
        $experience = Experience::factory()->withTechnologies()->create();
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

        $this->assertDatabaseMissing('experience_technology', [
            'experience_id' => $experience->id,
        ]);
    }

    #[Test]
    public function test_update_with_different_technologies()
    {
        $experience = Experience::factory()->withTechnologies()->create();
        $logo = Picture::factory()->create();
        $oldTechnologies = $experience->technologies;
        $newTechnologies = Technology::factory()->count(3)->create();

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
            'technologies' => $newTechnologies->pluck('id')->toArray(),
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
            'type' => ExperienceType::EMPLOI,
            'location' => 'Updated Location',
            'website_url' => 'https://updated-example.com',
        ]);

        foreach ($newTechnologies as $technology) {
            $this->assertDatabaseHas('experience_technology', [
                'experience_id' => $experience->id,
                'technology_id' => $technology->id,
            ]);
        }

        foreach ($oldTechnologies as $technology) {
            $this->assertDatabaseMissing('experience_technology', [
                'experience_id' => $experience->id,
                'technology_id' => $technology->id,
            ]);
        }
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

    #[Test]
    public function test_create_generates_slug_from_organization_and_title()
    {
        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Software Engineer',
            'organization_name' => 'Tech Company',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->postJson(route('dashboard.api.experiences.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('slug', 'tech-company-software-engineer');

        $this->assertDatabaseHas('experiences', [
            'slug' => 'tech-company-software-engineer',
        ]);
    }

    #[Test]
    public function test_create_generates_slug_with_counter_when_duplicate()
    {
        // Create first experience with a specific slug
        $existingExperience = Experience::factory()->create([
            'slug' => 'tech-company-software-engineer',
        ]);

        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Software Engineer',
            'organization_name' => 'Tech Company',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->postJson(route('dashboard.api.experiences.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('slug', 'tech-company-software-engineer-1');

        $this->assertDatabaseHas('experiences', [
            'slug' => 'tech-company-software-engineer-1',
        ]);
    }

    #[Test]
    public function test_create_increments_slug_counter_for_multiple_duplicates()
    {
        // Create experiences with conflicting slugs
        Experience::factory()->create(['slug' => 'tech-company-software-engineer']);
        Experience::factory()->create(['slug' => 'tech-company-software-engineer-1']);
        Experience::factory()->create(['slug' => 'tech-company-software-engineer-2']);

        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Software Engineer',
            'organization_name' => 'Tech Company',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->postJson(route('dashboard.api.experiences.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('slug', 'tech-company-software-engineer-3');

        $this->assertDatabaseHas('experiences', [
            'slug' => 'tech-company-software-engineer-3',
        ]);
    }

    #[Test]
    public function test_update_regenerates_slug_when_organization_name_changes()
    {
        $experience = Experience::factory()->create([
            'slug' => 'old-company-developer',
        ]);
        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Senior Developer',
            'organization_name' => 'New Company',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->putJson(route('dashboard.api.experiences.update', $experience), $data);

        $response->assertOk()
            ->assertJsonPath('slug', 'new-company-senior-developer');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'slug' => 'new-company-senior-developer',
        ]);
    }

    #[Test]
    public function test_update_keeps_slug_when_organization_name_unchanged()
    {
        $experience = Experience::factory()->create([
            'organization_name' => 'Tech Company',
            'slug' => 'tech-company-developer',
        ]);
        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Senior Developer',
            'organization_name' => 'Tech Company', // Same organization name
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->putJson(route('dashboard.api.experiences.update', $experience), $data);

        $response->assertOk()
            ->assertJsonPath('slug', 'tech-company-developer');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'slug' => 'tech-company-developer',
        ]);
    }

    #[Test]
    public function test_update_generates_slug_with_counter_avoiding_conflicts()
    {
        $experience = Experience::factory()->create([
            'slug' => 'old-company-developer',
        ]);

        // Create another experience with the target slug
        Experience::factory()->create([
            'slug' => 'new-company-senior-developer',
        ]);

        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Senior Developer',
            'organization_name' => 'New Company',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->putJson(route('dashboard.api.experiences.update', $experience), $data);

        $response->assertOk()
            ->assertJsonPath('slug', 'new-company-senior-developer-1');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'slug' => 'new-company-senior-developer-1',
        ]);
    }

    #[Test]
    public function test_update_generates_slug_if_missing()
    {
        // Create an experience with a slug that we'll forcefully remove via DB
        $experience = Experience::factory()->create([
            'slug' => 'temp-slug',
        ]);

        // Directly update database to simulate missing slug (bypassing model validation)
        \DB::table('experiences')->where('id', $experience->id)->update(['slug' => '']);
        $experience->refresh();

        $logo = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Developer',
            'organization_name' => 'Tech Corp',
            'logo_id' => $logo->id,
            'type' => 'emploi',
            'location' => 'Paris',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $response = $this->putJson(route('dashboard.api.experiences.update', $experience), $data);

        $response->assertOk()
            ->assertJsonPath('slug', 'tech-corp-developer');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'slug' => 'tech-corp-developer',
        ]);
    }
}
