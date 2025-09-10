<?php

namespace Tests\Browser\Dashboard;

use App\Models\ApiRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ApiLogsTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test accessing the API logs page
     */
    public function test_can_access_api_logs_page(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10)
                ->assertSee('Logs des requêtes API')
                ->assertPresent('[data-testid="api-logs-table"]')
                ->screenshot('api-logs-page');
        });
    }

    /**
     * Test API logs statistics display
     */
    public function test_api_logs_statistics_display(): void
    {
        $user = User::factory()->create();

        // Create multiple logs for statistics
        for ($i = 0; $i < 5; $i++) {
            ApiRequestLog::create([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'status' => 'success',
                'http_status_code' => 200,
                'input_tokens' => 100,
                'output_tokens' => 50,
                'response_time' => 1.0,
                'estimated_cost' => 0.0001,
                'system_prompt' => 'Test',
                'user_prompt' => 'Test',
                'cached' => $i % 2 === 0, // Some cached, some not
            ]);
        }

        ApiRequestLog::create([
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'status' => 'error',
            'http_status_code' => 500,
            'response_time' => 0.5,
            'system_prompt' => 'Test',
            'user_prompt' => 'Test',
            'cached' => false,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10)
                ->assertPresent('[data-testid="statistics-panel"]')
                ->assertSee('Total Requests')
                ->assertSee('6') // Total requests
                ->assertSee('Success Rate')
                ->assertSee('Cache Hit Rate')
                ->assertSee('Total Cost')
                ->screenshot('api-logs-statistics');
        });
    }
}
