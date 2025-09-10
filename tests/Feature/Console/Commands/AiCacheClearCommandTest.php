<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\AiCacheClearCommand;
use App\Services\AiTranslationCacheService;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AiCacheClearCommand::class)]
class AiCacheClearCommandTest extends TestCase
{

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_clears_all_cache_entries_when_all_option_is_provided_and_confirmed(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('clearAll')
            ->once()
            ->andReturn(42);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--all' => true])
            ->expectsConfirmation('Are you sure you want to clear ALL cache entries?', 'yes')
            ->expectsOutput('✓ Cleared 42 cache entries.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_cancels_clearing_all_cache_entries_when_not_confirmed(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldNotReceive('clearAll');

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--all' => true])
            ->expectsConfirmation('Are you sure you want to clear ALL cache entries?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_clears_expired_entries_with_default_30_days(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with(30 * 24 * 60 * 60)
            ->andReturn(15);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear')
            ->expectsOutput('✓ Cleared 15 cache entries older than 30 days.')
            ->assertSuccessful();
    }

    #[Test]
    #[DataProvider('validOlderThanValuesProvider')]
    public function it_clears_expired_entries_with_custom_older_than_value(int $days): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with($days * 24 * 60 * 60)
            ->andReturn(8);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--older-than' => $days])
            ->expectsOutput("✓ Cleared 8 cache entries older than {$days} days.")
            ->assertSuccessful();
    }

    public static function validOlderThanValuesProvider(): array
    {
        return [
            'one day' => [1],
            'one week' => [7],
            'two weeks' => [14],
            'sixty days' => [60],
            'one year' => [365],
        ];
    }

    #[Test]
    #[DataProvider('invalidOlderThanValuesProvider')]
    public function it_fails_when_older_than_is_not_numeric(string $invalidValue): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldNotReceive('clearExpired');

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--older-than' => $invalidValue])
            ->expectsOutput('The --older-than option must be a number.')
            ->assertFailed();
    }

    public static function invalidOlderThanValuesProvider(): array
    {
        return [
            'text string' => ['invalid'],
            'mixed alphanumeric' => ['30days'],
            'special characters' => ['!@#'],
            'empty string' => [''],
        ];
    }

    #[Test]
    public function it_shows_statistics_when_stats_option_is_provided(): void
    {
        $mockStats = [
            'total_entries' => 100,
            'total_hits' => 500,
            'average_hits' => 5.0,
            'oldest_entry' => '2023-01-01 00:00:00',
            'newest_entry' => '2024-01-01 00:00:00',
            'providers' => [
                ['provider' => 'openai', 'count' => 60, 'total_hits' => 300],
                ['provider' => 'anthropic', 'count' => 40, 'total_hits' => 200],
            ],
            'most_used' => [
                [
                    'cache_key' => 'translation_key_example_1234567890',
                    'provider' => 'openai',
                    'hits' => 50,
                    'created_at' => '2023-06-01 12:00:00',
                ],
            ],
        ];

        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('getStatistics')
            ->once()
            ->andReturn($mockStats);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with(30 * 24 * 60 * 60)
            ->andReturn(10);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--stats' => true])
            ->expectsOutput('AI Translation Cache Statistics')
            ->expectsOutput('================================')
            ->expectsOutput('Total Entries: 100')
            ->expectsOutput('Total Hits: 500')
            ->expectsOutput('Average Hits: 5.00')
            ->expectsOutput('Oldest Entry: 2023-01-01 00:00:00')
            ->expectsOutput('Newest Entry: 2024-01-01 00:00:00')
            ->expectsOutput('Provider Statistics:')
            ->expectsOutput('✓ Cleared 10 cache entries older than 30 days.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_warning_when_no_statistics_available(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('getStatistics')
            ->once()
            ->andReturn([]);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with(30 * 24 * 60 * 60)
            ->andReturn(0);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--stats' => true])
            ->expectsOutput('No statistics available.')
            ->expectsOutput('✓ Cleared 0 cache entries older than 30 days.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_partial_statistics_when_some_fields_are_missing(): void
    {
        $mockStats = [
            'total_entries' => 50,
            'total_hits' => 250,
            'average_hits' => 5.0,
            // No oldest_entry, newest_entry, providers, or most_used
        ];

        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('getStatistics')
            ->once()
            ->andReturn($mockStats);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with(30 * 24 * 60 * 60)
            ->andReturn(5);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--stats' => true])
            ->expectsOutput('AI Translation Cache Statistics')
            ->expectsOutput('================================')
            ->expectsOutput('Total Entries: 50')
            ->expectsOutput('Total Hits: 250')
            ->expectsOutput('Average Hits: 5.00')
            ->doesntExpectOutput('Oldest Entry:')
            ->doesntExpectOutput('Newest Entry:')
            ->doesntExpectOutput('Provider Statistics:')
            ->doesntExpectOutput('Most Used Cache Entries:')
            ->expectsOutput('✓ Cleared 5 cache entries older than 30 days.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_combines_stats_and_all_options(): void
    {
        $mockStats = [
            'total_entries' => 25,
            'total_hits' => 100,
            'average_hits' => 4.0,
        ];

        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('getStatistics')
            ->once()
            ->andReturn($mockStats);
        $mockService->shouldReceive('clearAll')
            ->once()
            ->andReturn(25);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--stats' => true, '--all' => true])
            ->expectsOutput('AI Translation Cache Statistics')
            ->expectsOutput('================================')
            ->expectsOutput('Total Entries: 25')
            ->expectsConfirmation('Are you sure you want to clear ALL cache entries?', 'yes')
            ->expectsOutput('✓ Cleared 25 cache entries.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_prioritizes_all_option_over_older_than(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('clearAll')
            ->once()
            ->andReturn(30);
        $mockService->shouldNotReceive('clearExpired');

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--all' => true, '--older-than' => 10])
            ->expectsConfirmation('Are you sure you want to clear ALL cache entries?', 'yes')
            ->expectsOutput('✓ Cleared 30 cache entries.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_zero_deleted_entries_gracefully(): void
    {
        $mockService = Mockery::mock(AiTranslationCacheService::class);
        $mockService->shouldReceive('clearExpired')
            ->once()
            ->with(7 * 24 * 60 * 60)
            ->andReturn(0);

        $this->app->instance(AiTranslationCacheService::class, $mockService);

        $this->artisan('ai:cache:clear', ['--older-than' => 7])
            ->expectsOutput('✓ Cleared 0 cache entries older than 7 days.')
            ->assertSuccessful();
    }
}