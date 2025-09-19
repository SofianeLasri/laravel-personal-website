<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ImageCacheStatsCommand;
use App\Services\ImageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(ImageCacheStatsCommand::class)]
class ImageCacheStatsCommandTest extends TestCase
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
    public function it_displays_disabled_cache_warning(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => false,
                'total_keys' => 0,
                'total_memory' => 0,
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Image Cache Statistics')
            ->expectsOutput('========================')
            ->expectsOutput('Image cache is disabled')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_displays_enabled_cache_stats_with_no_images(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => true,
                'total_keys' => 0,
                'total_memory' => 0,
                'total_memory_human' => '0 B',
                'ttl' => 604800,
                'compression_enabled' => true,
                'hash_algorithm' => 'md5',
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Image Cache Statistics')
            ->expectsOutput('========================')
            ->expectsOutput('Status: Enabled')
            ->expectsOutput('Total cached images: 0')
            ->expectsOutput('Total memory usage: 0 B')
            ->expectsOutput('TTL (seconds): 604800')
            ->expectsOutput('Compression: Enabled')
            ->expectsOutput('Hash algorithm: md5')
            ->doesntExpectOutput('Average memory per image:')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_enabled_cache_stats_with_images(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => true,
                'total_keys' => 5,
                'total_memory' => 1024000,
                'total_memory_human' => '1000.00 KB',
                'ttl' => 604800,
                'compression_enabled' => true,
                'hash_algorithm' => 'sha256',
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Image Cache Statistics')
            ->expectsOutput('========================')
            ->expectsOutput('Status: Enabled')
            ->expectsOutput('Total cached images: 5')
            ->expectsOutput('Total memory usage: 1000.00 KB')
            ->expectsOutput('TTL (seconds): 604800')
            ->expectsOutput('Compression: Enabled')
            ->expectsOutput('Hash algorithm: sha256')
            ->expectsOutput('Average memory per image: 200 KB')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_compression_enabled_status(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => true,
                'total_keys' => 0,
                'total_memory' => 0,
                'total_memory_human' => '0 B',
                'ttl' => 604800,
                'compression_enabled' => true,
                'hash_algorithm' => 'md5',
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Compression: Enabled')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_compression_disabled_status(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => true,
                'total_keys' => 0,
                'total_memory' => 0,
                'total_memory_human' => '0 B',
                'ttl' => 604800,
                'compression_enabled' => false,
                'hash_algorithm' => 'md5',
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Compression: Disabled')
            ->assertExitCode(0);
    }

    #[Test]
    #[DataProvider('formatBytesDataProvider')]
    public function it_formats_bytes_correctly(int $input, string $expected): void
    {
        $command = new ImageCacheStatsCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        $result = $method->invoke($command, $input);

        $this->assertEquals($expected, $result);
    }

    public static function formatBytesDataProvider(): array
    {
        return [
            'zero bytes' => [0, '0 B'],
            'negative bytes (should be treated as zero)' => [-100, '0 B'],
            'small bytes' => [512, '512 B'],
            'exactly 1 KB' => [1024, '1 KB'],
            'kilobytes with decimals' => [1536, '1.5 KB'],
            'exactly 1 MB' => [1048576, '1 MB'],
            'megabytes with decimals' => [1572864, '1.5 MB'],
            'exactly 1 GB' => [1073741824, '1 GB'],
            'gigabytes with decimals' => [1610612736, '1.5 GB'],
            'large value with rounding' => [1234567890, '1.15 GB'],
            'very large value' => [9999999999999, '9313.23 GB'],
        ];
    }

    #[Test]
    public function it_calculates_average_memory_correctly_with_large_numbers(): void
    {
        $this->mockImageCacheService
            ->shouldReceive('getCacheStats')
            ->once()
            ->andReturn([
                'enabled' => true,
                'total_keys' => 3,
                'total_memory' => 3221225472, // 3 GB
                'total_memory_human' => '3.00 GB',
                'ttl' => 604800,
                'compression_enabled' => false,
                'hash_algorithm' => 'sha1',
            ]);

        $this->artisan(ImageCacheStatsCommand::class)
            ->expectsOutput('Average memory per image: 1 GB')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_all_hash_algorithms(): void
    {
        $hashAlgorithms = ['md5', 'sha1', 'sha256', 'sha512'];

        foreach ($hashAlgorithms as $algorithm) {
            $this->mockImageCacheService
                ->shouldReceive('getCacheStats')
                ->once()
                ->andReturn([
                    'enabled' => true,
                    'total_keys' => 0,
                    'total_memory' => 0,
                    'total_memory_human' => '0 B',
                    'ttl' => 604800,
                    'compression_enabled' => true,
                    'hash_algorithm' => $algorithm,
                ]);

            $this->artisan(ImageCacheStatsCommand::class)
                ->expectsOutput("Hash algorithm: {$algorithm}")
                ->assertExitCode(0);
        }
    }

    #[Test]
    public function it_displays_different_ttl_values(): void
    {
        $ttlValues = [3600, 86400, 604800, 2592000]; // 1h, 1d, 1w, 1m

        foreach ($ttlValues as $ttl) {
            $this->mockImageCacheService
                ->shouldReceive('getCacheStats')
                ->once()
                ->andReturn([
                    'enabled' => true,
                    'total_keys' => 0,
                    'total_memory' => 0,
                    'total_memory_human' => '0 B',
                    'ttl' => $ttl,
                    'compression_enabled' => true,
                    'hash_algorithm' => 'md5',
                ]);

            $this->artisan(ImageCacheStatsCommand::class)
                ->expectsOutput("TTL (seconds): {$ttl}")
                ->assertExitCode(0);
        }
    }
}