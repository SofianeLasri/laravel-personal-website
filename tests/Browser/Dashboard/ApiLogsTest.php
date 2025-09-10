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
     * Test API logs table displays data
     */
    public function test_api_logs_table_displays_data(): void
    {
        $user = User::factory()->create();

        // Create test API logs
        ApiRequestLog::create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'status' => 'success',
            'http_status_code' => 200,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'response_time' => 1.234,
            'estimated_cost' => 0.0001,
            'system_prompt' => 'You are a helpful assistant',
            'user_prompt' => 'Test prompt',
            'cached' => false,
        ]);

        ApiRequestLog::create([
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet',
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'status' => 'error',
            'http_status_code' => 500,
            'error_message' => 'Internal server error',
            'response_time' => 0.5,
            'system_prompt' => 'You are a helpful assistant',
            'user_prompt' => 'Test prompt 2',
            'cached' => false,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10)
                ->assertSee('openai')
                ->assertSee('gpt-4o-mini')
                ->assertSee('success')
                ->assertSee('anthropic')
                ->assertSee('claude-3-5-sonnet')
                ->assertSee('error')
                ->screenshot('api-logs-with-data');
        });
    }

    /**
     * Test filtering API logs
     */
    public function test_can_filter_api_logs(): void
    {
        $user = User::factory()->create();

        // Create test data
        ApiRequestLog::create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'status' => 'success',
            'http_status_code' => 200,
            'response_time' => 1.0,
            'system_prompt' => 'Test',
            'user_prompt' => 'Test',
            'cached' => false,
        ]);

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
                // Filter by provider
                ->select('[data-testid="provider-filter"]', 'openai')
                ->pause(500)
                ->assertSee('openai')
                ->assertDontSee('anthropic')
                // Reset and filter by status
                ->select('[data-testid="provider-filter"]', '')
                ->pause(500)
                ->select('[data-testid="status-filter"]', 'error')
                ->pause(500)
                ->assertSee('anthropic')
                ->assertDontSee('openai')
                ->screenshot('api-logs-filtered');
        });
    }

    /**
     * Test viewing API log details
     */
    public function test_can_view_api_log_details(): void
    {
        $user = User::factory()->create();

        ApiRequestLog::create([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'status' => 'success',
            'http_status_code' => 200,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'response_time' => 1.234,
            'estimated_cost' => 0.0001,
            'system_prompt' => 'You are a helpful assistant for testing',
            'user_prompt' => 'This is a test prompt for viewing details',
            'cached' => false,
            'metadata' => ['test' => 'data'],
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10)
                ->click('[data-testid="view-details-1"]')
                ->waitForText('Détails de la requête', 5)
                ->assertSee('You are a helpful assistant for testing')
                ->assertSee('This is a test prompt for viewing details')
                ->assertSee('100') // input tokens
                ->assertSee('50')  // output tokens
                ->assertSee('1.234') // response time
                ->screenshot('api-log-details');
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

    /**
     * Test console errors on page load
     */
    public function test_no_console_errors_on_page_load(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10);

            // Check for console errors
            $logs = $browser->driver->manage()->getLog('browser');
            $errors = array_filter($logs, function ($log) {
                return $log['level'] === 'SEVERE';
            });

            if (! empty($errors)) {
                $browser->screenshot('console-errors');
                $this->fail('Console errors found: '.json_encode($errors, JSON_PRETTY_PRINT));
            }

            $this->assertEmpty($errors, 'No console errors should be present');
        });
    }

    /**
     * Test pagination of API logs
     */
    public function test_api_logs_pagination(): void
    {
        $user = User::factory()->create();

        // Create 25 logs to test pagination
        for ($i = 1; $i <= 25; $i++) {
            ApiRequestLog::create([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'status' => 'success',
                'http_status_code' => 200,
                'response_time' => 1.0,
                'system_prompt' => "Test $i",
                'user_prompt' => "Prompt $i",
                'cached' => false,
            ]);
        }

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard/api-logs')
                ->waitForText('Logs des requêtes API', 10)
                ->assertPresent('[data-testid="pagination"]')
                ->assertSee('1')
                ->assertSee('2')
                ->click('[data-testid="page-2"]')
                ->pause(500)
                ->assertQueryStringHas('page', '2')
                ->screenshot('api-logs-page-2');
        });
    }
}
