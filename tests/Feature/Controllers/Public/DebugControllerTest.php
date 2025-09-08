<?php

namespace Tests\Feature\Controllers\Public;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

#[CoversClass(DebugController::class)]
class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_debug_page_accessible_in_local_environment(): void
    {
        // Set environment to local
        config(['app.env' => 'local']);

        $response = $this->get('/debug');

        $response->assertStatus(200);
        $response->assertViewIs('debug.index');
        $response->assertViewHas([
            'modelStats',
            'creationTypes',
            'technologyTypes',
            'techCreationRelations',
            'envVars',
            'dbInfo',
            'latestCreations',
            'topTechnologies',
            'translationStats',
            'storageInfo',
            'routes'
        ]);
    }

    #[Test]
    public function test_debug_page_accessible_in_development_environment(): void
    {
        // Set environment to development
        config(['app.env' => 'development']);

        $response = $this->get('/debug');

        $response->assertStatus(200);
        $response->assertViewIs('debug.index');
    }

    #[Test]
    public function test_debug_page_returns_404_in_production(): void
    {
        // Set environment to production
        config(['app.env' => 'production']);

        $response = $this->get('/debug');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_debug_page_returns_404_in_staging(): void
    {
        // Set environment to staging
        config(['app.env' => 'staging']);

        $response = $this->get('/debug');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_debug_page_returns_404_in_testing(): void
    {
        // Set environment to testing (other than local/development)
        config(['app.env' => 'testing']);

        $response = $this->get('/debug');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_debug_page_contains_model_statistics(): void
    {
        // Set environment to local
        config(['app.env' => 'local']);

        // Create some test data
        \App\Models\Creation::factory()->count(3)->create();
        \App\Models\Technology::factory()->count(5)->create();
        \App\Models\Person::factory()->count(2)->create();

        $response = $this->get('/debug');

        $response->assertStatus(200);
        
        // Check that model stats are populated
        $viewData = $response->viewData('modelStats');
        $this->assertIsArray($viewData);
        $this->assertArrayHasKey('Creations', $viewData);
        $this->assertArrayHasKey('Technologies', $viewData);
        $this->assertArrayHasKey('People', $viewData);
        
        // Check counts match
        $this->assertEquals(3, $viewData['Creations']);
        $this->assertEquals(5, $viewData['Technologies']);
        $this->assertEquals(2, $viewData['People']);
    }

    #[Test]
    public function test_debug_page_filters_sensitive_environment_variables(): void
    {
        // Set environment to local
        config(['app.env' => 'local']);

        $response = $this->get('/debug');

        $response->assertStatus(200);
        
        $envVars = $response->viewData('envVars');
        
        // Check that only safe environment variables are shown
        $this->assertArrayHasKey('APP_ENV', $envVars);
        $this->assertArrayHasKey('APP_DEBUG', $envVars);
        $this->assertArrayHasKey('DB_CONNECTION', $envVars);
        
        // Make sure sensitive data is not exposed
        $this->assertArrayNotHasKey('APP_KEY', $envVars);
        $this->assertArrayNotHasKey('DB_PASSWORD', $envVars);
        $this->assertArrayNotHasKey('MAIL_PASSWORD', $envVars);
        $this->assertArrayNotHasKey('AWS_SECRET_ACCESS_KEY', $envVars);
    }

    #[Test]
    public function test_debug_page_shows_database_information(): void
    {
        // Set environment to local
        config(['app.env' => 'local']);

        $response = $this->get('/debug');

        $response->assertStatus(200);
        
        $dbInfo = $response->viewData('dbInfo');
        
        $this->assertIsArray($dbInfo);
        $this->assertArrayHasKey('connection', $dbInfo);
        $this->assertArrayHasKey('database', $dbInfo);
        $this->assertArrayHasKey('driver', $dbInfo);
        $this->assertArrayHasKey('tables_count', $dbInfo);
    }

    #[Test]
    public function test_debug_page_filters_internal_routes(): void
    {
        // Set environment to local
        config(['app.env' => 'local']);

        $response = $this->get('/debug');

        $response->assertStatus(200);
        
        $routes = $response->viewData('routes');
        
        // Check that internal routes are filtered out
        foreach ($routes as $route) {
            $this->assertStringStartsNotWith('_', $route['uri']);
            $this->assertStringStartsNotWith('sanctum', $route['uri']);
            $this->assertStringStartsNotWith('livewire', $route['uri']);
        }
    }
}