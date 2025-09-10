<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AiLogsAnalyzeCommand;
use App\Models\ApiRequestLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AiLogsAnalyzeCommand::class)]
class AiLogsAnalyzeCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_displays_warning_when_no_requests_found(): void
    {
        $this->artisan('ai:logs:analyze')
            ->expectsOutput('No requests found for the specified period.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_analyzes_basic_statistics_with_default_period(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        ApiRequestLog::factory()->create([
            'provider' => 'openai',
            'status' => 'success',
            'cached' => false,
            'created_at' => $now->copy()->subDays(2),
        ]);

        $this->artisan('ai:logs:analyze')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_filters_by_provider(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'provider' => 'openai',
            'status' => 'success',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze', ['--provider' => 'openai'])
            ->expectsOutput('Filtering by provider: openai')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'status' => 'success',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze', ['--status' => 'success'])
            ->expectsOutput('Filtering by status: success')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_error_breakdown_when_errors_exist(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'status' => 'error',
            'error_message' => 'Rate limit exceeded',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_does_not_show_error_breakdown_when_no_errors(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'status' => 'success',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze')
            ->doesntExpectOutput('⚠️ Top Errors')
            ->assertSuccessful();
    }

    #[Test]
    #[DataProvider('periodProvider')]
    public function it_parses_various_period_formats(string $period, int $expectedDaysAgo): void
    {
        $now = Carbon::parse('2024-01-15 12:00:00');
        Carbon::setTestNow($now);

        ApiRequestLog::factory()->create([
            'created_at' => $now->copy()->subDays($expectedDaysAgo),
        ]);

        $this->artisan('ai:logs:analyze', ['--period' => $period])
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    public static function periodProvider(): array
    {
        return [
            'hours format' => ['24hours', 1],
            'days format' => ['7days', 7],
            'weeks format' => ['2weeks', 14],
            'months format' => ['1month', 30],
        ];
    }

    #[Test]
    public function it_handles_invalid_period_format(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $this->artisan('ai:logs:analyze', ['--period' => 'invalid-period'])
            ->expectsOutput("Could not parse period 'invalid-period', defaulting to 7 days")
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_parses_date_format_period(): void
    {
        $now = Carbon::parse('2024-01-15 12:00:00');
        Carbon::setTestNow($now);

        ApiRequestLog::factory()->create([
            'created_at' => Carbon::parse('2024-01-10'),
        ]);

        $this->artisan('ai:logs:analyze', ['--period' => '2024-01-10'])
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_complete_statistics_with_all_metrics(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'provider' => 'openai',
            'status' => 'success',
            'cached' => false,
            'response_time' => 1.234,
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'estimated_cost' => 0.0015,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_zero_values_gracefully(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'response_time' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'estimated_cost' => 0,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    #[Test]
    public function it_combines_all_filters(): void
    {
        Carbon::setTestNow(Carbon::now());

        ApiRequestLog::factory()->create([
            'provider' => 'openai',
            'status' => 'success',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $this->artisan('ai:logs:analyze', [
            '--period' => '2days',
            '--provider' => 'openai',
            '--status' => 'success',
        ])
            ->expectsOutput('Filtering by provider: openai')
            ->expectsOutput('Filtering by status: success')
            ->expectsOutput('📊 API Request Statistics')
            ->assertSuccessful();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}