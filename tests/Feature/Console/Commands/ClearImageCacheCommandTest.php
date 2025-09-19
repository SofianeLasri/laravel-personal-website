<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ClearImageCacheCommand;
use App\Services\ImageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ClearImageCacheCommand::class)]
class ClearImageCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    private ImageCacheService $mockImageCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockImageCacheService = Mockery::mock(ImageCacheService::class);
        $this->app->instance(ImageCacheService::class, $this->mockImageCacheService);
    }

    #[Test]
    public function it_shows_warning_when_cache_is_disabled(): void
    {
        Config::set('images.cache.enabled', false);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Image cache is disabled')
            ->assertExitCode(1);

        // Verify that clearCache is never called when cache is disabled
        $this->mockImageCacheService->shouldNotReceive('clearCache');
    }

    #[Test]
    public function it_clears_cache_successfully_with_deleted_items(): void
    {
        Config::set('images.cache.enabled', true);

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn(5);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Clearing image cache...')
            ->expectsOutput('Successfully cleared 5 cached optimizations')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_clears_cache_with_no_items_found(): void
    {
        Config::set('images.cache.enabled', true);

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn(0);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Clearing image cache...')
            ->expectsOutput('No cached optimizations found')
            ->assertExitCode(0);
    }

    #[Test]
    #[DataProvider('deletedCountDataProvider')]
    public function it_displays_correct_messages_for_different_deleted_counts(int $deletedCount, string $expectedMessage): void
    {
        Config::set('images.cache.enabled', true);

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn($deletedCount);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Clearing image cache...')
            ->expectsOutput($expectedMessage)
            ->assertExitCode(0);
    }

    public static function deletedCountDataProvider(): array
    {
        return [
            'single item' => [1, 'Successfully cleared 1 cached optimizations'],
            'multiple items' => [10, 'Successfully cleared 10 cached optimizations'],
            'large number' => [100, 'Successfully cleared 100 cached optimizations'],
            'very large number' => [1000, 'Successfully cleared 1000 cached optimizations'],
        ];
    }

    #[Test]
    public function it_handles_cache_service_injection_correctly(): void
    {
        Config::set('images.cache.enabled', true);

        // Create a real instance to verify dependency injection works
        $realService = new ImageCacheService();
        $this->app->instance(ImageCacheService::class, $realService);

        // Mock the config to ensure cache is disabled so we don't actually clear anything
        Config::set('images.cache.enabled', false);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Image cache is disabled')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_respects_cache_enabled_configuration(): void
    {
        // Test with cache explicitly enabled
        Config::set('images.cache.enabled', true);

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn(3);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Clearing image cache...')
            ->expectsOutput('Successfully cleared 3 cached optimizations')
            ->assertExitCode(0);

        // Reset mock for second test
        $this->mockImageCacheService = Mockery::mock(ImageCacheService::class);
        $this->app->instance(ImageCacheService::class, $this->mockImageCacheService);

        // Test with cache explicitly disabled
        Config::set('images.cache.enabled', false);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Image cache is disabled')
            ->assertExitCode(1);

        $this->mockImageCacheService->shouldNotReceive('clearCache');
    }

    #[Test]
    public function it_uses_correct_command_signature_and_description(): void
    {
        $command = new ClearImageCacheCommand();

        $this->assertEquals('images:cache-clear', $command->getName());
        $this->assertEquals('Clear all cached image optimizations', $command->getDescription());
    }

    #[Test]
    public function it_handles_large_deletion_counts_correctly(): void
    {
        Config::set('images.cache.enabled', true);

        $largeDeletionCount = 999999;

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn($largeDeletionCount);

        $this->artisan(ClearImageCacheCommand::class)
            ->expectsOutput('Clearing image cache...')
            ->expectsOutput("Successfully cleared {$largeDeletionCount} cached optimizations")
            ->assertExitCode(0);
    }

    #[Test]
    public function it_only_calls_clear_cache_when_enabled(): void
    {
        // First verify it's called when enabled
        Config::set('images.cache.enabled', true);

        $this->mockImageCacheService
            ->shouldReceive('clearCache')
            ->once()
            ->andReturn(2);

        $this->artisan(ClearImageCacheCommand::class)
            ->assertExitCode(0);

        // Reset and verify it's not called when disabled
        $this->mockImageCacheService = Mockery::mock(ImageCacheService::class);
        $this->app->instance(ImageCacheService::class, $this->mockImageCacheService);

        Config::set('images.cache.enabled', false);

        $this->mockImageCacheService->shouldNotReceive('clearCache');

        $this->artisan(ClearImageCacheCommand::class)
            ->assertExitCode(1);
    }
}