<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\HomeController;
use App\Models\Creation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('public.home'));

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('public/Home')
                    ->has('socialMediaLinks')
                    ->has('yearsOfExperience')
                    ->has('developmentCreationsCount')
                    ->has('masteredFrameworksCount')
                    ->has('laravelCreations')
                    ->has('technologyExperiences')
                    ->has('experiences')
            );
    }

    #[Test]
    public function test_legacy_project_parameter_with_config_mapping_redirects_to_new_slug()
    {
        // Set up config mapping
        config(['legacy-projects.mappings' => ['bjloquent' => 'better-jloquent']]);

        $response = $this->get('/?project=bjloquent');

        $response->assertRedirect(route('public.projects.show', ['slug' => 'better-jloquent']));
        $response->assertStatus(301);
    }

    #[Test]
    public function test_legacy_project_parameter_redirects_to_project_page()
    {
        // Clear any config mappings to test database fallback
        config(['legacy-projects.mappings' => []]);

        $creation = Creation::factory()->create(['slug' => 'old-project']);

        $response = $this->get('/?project=old-project');

        $response->assertRedirect(route('public.projects.show', ['slug' => 'old-project']));
        $response->assertStatus(301);
    }

    #[Test]
    public function test_config_mapping_takes_precedence_over_database()
    {
        // Create a creation with the old slug
        $creation = Creation::factory()->create(['slug' => 'bjloquent']);

        // Set up config mapping that should override database
        config(['legacy-projects.mappings' => ['bjloquent' => 'better-jloquent']]);

        $response = $this->get('/?project=bjloquent');

        // Should redirect to the config mapping, not the database slug
        $response->assertRedirect(route('public.projects.show', ['slug' => 'better-jloquent']));
        $response->assertStatus(301);
    }

    #[Test]
    public function test_legacy_project_parameter_with_nonexistent_slug_continues_normally()
    {
        $response = $this->get('/?project=nonexistent');

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('public/Home')
            );
    }

    #[Test]
    public function test_home_page_without_project_parameter_works_normally()
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('public/Home')
            );
    }
}
