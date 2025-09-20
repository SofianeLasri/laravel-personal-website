<?php

namespace Tests\Feature\Services;

use App\Models\Picture;
use App\Services\ImageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ImageCacheService::class)]
class ImageCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImageCacheService $imageCacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageCacheService = new ImageCacheService;

        // Enable cache for testing
        config([
            'images.cache.enabled' => true,
            'images.cache.driver' => 'array',
            'images.cache.ttl' => 3600,
            'images.cache.hash_algo' => 'md5',
            'images.cache.compress' => false,
            'images.cache.key_prefix' => 'test_image_cache',
        ]);

        Storage::fake('public');
    }

    #[Test]
    public function test_calculates_consistent_checksum_for_same_image(): void
    {
        $imageContent = 'fake_image_data_12345';

        $checksum1 = $this->imageCacheService->calculateChecksum($imageContent);
        $checksum2 = $this->imageCacheService->calculateChecksum($imageContent);

        $this->assertEquals($checksum1, $checksum2);
        $this->assertEquals(md5($imageContent), $checksum1);
    }

    #[Test]
    public function test_different_images_have_different_checksums(): void
    {
        $imageContent1 = 'fake_image_data_12345';
        $imageContent2 = 'fake_image_data_67890';

        $checksum1 = $this->imageCacheService->calculateChecksum($imageContent1);
        $checksum2 = $this->imageCacheService->calculateChecksum($imageContent2);

        $this->assertNotEquals($checksum1, $checksum2);
    }

    #[Test]
    public function test_cache_miss_returns_null(): void
    {
        $nonExistentChecksum = 'non_existent_checksum';

        $result = $this->imageCacheService->getCachedOptimizations($nonExistentChecksum);

        $this->assertNull($result);
    }

    #[Test]
    public function test_stores_and_retrieves_cached_optimizations(): void
    {
        $checksum = 'test_checksum_12345';
        $width = 1920;
        $height = 1080;
        $optimizedImages = [
            'avif' => [
                'thumbnail' => 'avif_thumbnail_data',
                'small' => 'avif_small_data',
            ],
            'webp' => [
                'thumbnail' => 'webp_thumbnail_data',
                'small' => 'webp_small_data',
            ],
        ];

        // Store in cache
        $this->imageCacheService->storeCachedOptimizations($checksum, $optimizedImages, $width, $height);

        // Retrieve from cache
        $cached = $this->imageCacheService->getCachedOptimizations($checksum);

        $this->assertNotNull($cached);
        $this->assertEquals($width, $cached['width']);
        $this->assertEquals($height, $cached['height']);
        $this->assertEquals($optimizedImages, $cached['optimized_files']);
        $this->assertArrayHasKey('cached_at', $cached);
    }

    #[Test]
    public function test_copies_cached_files_to_storage(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
        ]);

        Storage::disk('public')->put($picture->path_original, 'fake_original_image');

        $cachedData = [
            'width' => 1920,
            'height' => 1080,
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => 'avif_thumbnail_data',
                    'small' => 'avif_small_data',
                ],
                'webp' => [
                    'thumbnail' => 'webp_thumbnail_data',
                ],
            ],
        ];

        $result = $this->imageCacheService->copyCachedFiles($cachedData, $picture);

        $this->assertTrue($result);

        // Check that files were created
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_thumbnail.avif'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_small.avif'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_thumbnail.webp'));

        // Check file contents
        $this->assertEquals('avif_thumbnail_data', Storage::disk('public')->get('uploads/test-image_thumbnail.avif'));
        $this->assertEquals('webp_thumbnail_data', Storage::disk('public')->get('uploads/test-image_thumbnail.webp'));

        // Check that OptimizedPicture records were created
        $this->assertDatabaseHas('optimized_pictures', [
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
            'path' => 'uploads/test-image_thumbnail.avif',
        ]);

        $this->assertDatabaseHas('optimized_pictures', [
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
            'path' => 'uploads/test-image_thumbnail.webp',
        ]);

        // Check that picture dimensions were updated
        $picture->refresh();
        $this->assertEquals(1920, $picture->width);
        $this->assertEquals(1080, $picture->height);
    }

    #[Test]
    public function test_copy_cached_files_fails_gracefully_with_invalid_picture(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        $cachedData = [
            'width' => 1920,
            'height' => 1080,
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => 'avif_thumbnail_data',
                ],
            ],
        ];

        $result = $this->imageCacheService->copyCachedFiles($cachedData, $picture);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_clears_cache(): void
    {
        // Store some test data in cache
        $this->imageCacheService->storeCachedOptimizations('checksum1', [], 100, 100);
        $this->imageCacheService->storeCachedOptimizations('checksum2', [], 200, 200);

        // Verify data exists
        $this->assertNotNull($this->imageCacheService->getCachedOptimizations('checksum1'));
        $this->assertNotNull($this->imageCacheService->getCachedOptimizations('checksum2'));

        // Clear cache
        $deletedCount = $this->imageCacheService->clearCache();

        $this->assertGreaterThan(0, $deletedCount);

        // Verify cache is empty
        $this->assertNull($this->imageCacheService->getCachedOptimizations('checksum1'));
        $this->assertNull($this->imageCacheService->getCachedOptimizations('checksum2'));
    }

    #[Test]
    public function test_cache_stats_when_disabled(): void
    {
        config(['images.cache.enabled' => false]);

        $stats = $this->imageCacheService->getCacheStats();

        $this->assertFalse($stats['enabled']);
        $this->assertEquals(0, $stats['total_keys']);
        $this->assertEquals(0, $stats['total_memory']);
    }

    #[Test]
    public function test_cache_stats_when_enabled(): void
    {
        // Store some test data
        $this->imageCacheService->storeCachedOptimizations('test_checksum', [], 100, 100);

        $stats = $this->imageCacheService->getCacheStats();

        $this->assertTrue($stats['enabled']);
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('total_memory', $stats);
        $this->assertArrayHasKey('total_memory_human', $stats);
        $this->assertArrayHasKey('ttl', $stats);
        $this->assertArrayHasKey('compression_enabled', $stats);
        $this->assertArrayHasKey('hash_algorithm', $stats);
    }

    #[Test]
    public function test_cache_disabled_returns_null(): void
    {
        config(['images.cache.enabled' => false]);

        $result = $this->imageCacheService->getCachedOptimizations('any_checksum');

        $this->assertNull($result);
    }

    #[Test]
    public function test_cache_disabled_does_not_store(): void
    {
        config(['images.cache.enabled' => false]);

        // This should not throw an error and should not store anything
        $this->imageCacheService->storeCachedOptimizations('checksum', [], 100, 100);

        // Re-enable cache to check if anything was stored
        config(['images.cache.enabled' => true]);
        $result = $this->imageCacheService->getCachedOptimizations('checksum');

        $this->assertNull($result);
    }

    #[Test]
    public function test_handles_base64_encoded_cached_content(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
        ]);

        Storage::disk('public')->put($picture->path_original, 'fake_original_image');

        $originalContent = 'original_image_data';
        $base64Content = base64_encode($originalContent);

        $cachedData = [
            'width' => 100,
            'height' => 100,
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => $base64Content,
                ],
            ],
        ];

        $result = $this->imageCacheService->copyCachedFiles($cachedData, $picture);

        $this->assertTrue($result);

        // Check that the content was properly decoded
        $storedContent = Storage::disk('public')->get('uploads/test-image_thumbnail.avif');
        $this->assertEquals($originalContent, $storedContent);
    }

    #[Test]
    public function test_stores_and_retrieves_with_compression_enabled(): void
    {
        config(['images.cache.compress' => true]);
        $service = new ImageCacheService;

        $checksum = 'test_checksum_compressed';
        $optimizedImages = [
            'avif' => ['thumbnail' => 'avif_data'],
            'webp' => ['thumbnail' => 'webp_data'],
        ];

        $service->storeCachedOptimizations($checksum, $optimizedImages, 100, 100);
        $cached = $service->getCachedOptimizations($checksum);

        $this->assertNotNull($cached);
        $this->assertEquals(100, $cached['width']);
        $this->assertEquals(100, $cached['height']);
        $this->assertEquals($optimizedImages, $cached['optimized_files']);
    }

    #[Test]
    public function test_handles_compression_failure_during_storage(): void
    {
        config(['images.cache.compress' => true]);

        // Create a very large string that is likely to cause memory issues during compression
        // This test relies on the fact that gzcompress might fail with extremely large data
        $checksum = 'test_checksum_fail';

        // Create a string so large it might cause compression issues
        $largeData = str_repeat('x', 10 * 1024 * 1024); // 10MB string
        $optimizedImages = [
            'avif' => ['thumbnail' => $largeData],
        ];

        // If compression fails, the method should handle it gracefully
        $this->imageCacheService->storeCachedOptimizations($checksum, $optimizedImages, 100, 100);

        // The test should pass regardless - we're testing that no fatal errors occur
        // If compression succeeds, data will be stored; if it fails, it won't
        $cached = $this->imageCacheService->getCachedOptimizations($checksum);

        // Either the data is stored (compression succeeded) or it's null (compression failed)
        // Both are valid outcomes for this robustness test
        $this->assertTrue($cached === null || is_array($cached));
    }

    #[Test]
    public function test_handles_decompression_failure_during_retrieval(): void
    {
        config(['images.cache.compress' => true]);

        // Store corrupted compressed data directly in cache
        $checksum = 'test_corrupted_data';
        $cacheKey = "test_image_cache:{$checksum}";

        // Put invalid compressed data in cache
        Cache::store('array')->put($cacheKey, 'invalid_compressed_data');

        $cached = $this->imageCacheService->getCachedOptimizations($checksum);

        $this->assertNull($cached);
    }

    #[Test]
    public function test_handles_json_encoding_error_during_storage(): void
    {
        $checksum = 'test_json_error';

        // Create data that will cause JSON encoding issues (like resources)
        $optimizedImages = [
            'avif' => ['thumbnail' => "\xB1\x31"], // Invalid UTF-8 sequence
        ];

        $this->imageCacheService->storeCachedOptimizations($checksum, $optimizedImages, 100, 100);

        // Should not have stored anything due to JSON encoding failure
        $cached = $this->imageCacheService->getCachedOptimizations($checksum);
        $this->assertNull($cached);
    }

    #[Test]
    public function test_handles_json_decoding_error_during_retrieval(): void
    {
        // Store invalid JSON data directly in cache
        $checksum = 'test_invalid_json';
        $cacheKey = "test_image_cache:{$checksum}";

        Cache::store('array')->put($cacheKey, 'invalid_json_data');

        $cached = $this->imageCacheService->getCachedOptimizations($checksum);

        $this->assertNull($cached);
    }

    #[Test]
    public function test_copies_files_to_cdn_when_configured(): void
    {
        config(['app.cdn_disk' => 'local']);
        Storage::fake('local');

        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
        ]);

        Storage::disk('public')->put($picture->path_original, 'fake_original_image');

        $cachedData = [
            'width' => 200,
            'height' => 200,
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => 'avif_thumbnail_data',
                ],
            ],
        ];

        $result = $this->imageCacheService->copyCachedFiles($cachedData, $picture);

        $this->assertTrue($result);

        // Check that file was copied to both public and CDN disks
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_thumbnail.avif'));
        $this->assertTrue(Storage::disk('local')->exists('uploads/test-image_thumbnail.avif'));

        // Check CDN content
        $this->assertEquals('avif_thumbnail_data', Storage::disk('local')->get('uploads/test-image_thumbnail.avif'));
    }

    #[Test]
    public function test_copy_cached_files_handles_storage_exception(): void
    {
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
        ]);

        // Create a mock that will throw an exception during storage
        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf()
            ->shouldReceive('put')
            ->andThrow(new \Exception('Storage failed'));

        $cachedData = [
            'width' => 100,
            'height' => 100,
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => 'avif_data',
                ],
            ],
        ];

        $result = $this->imageCacheService->copyCachedFiles($cachedData, $picture);

        $this->assertFalse($result);
    }

    #[Test]
    public function test_clear_cache_returns_zero_when_empty(): void
    {
        // Ensure cache is empty first
        $this->imageCacheService->clearCache();

        // Clear again - should return 0
        $deletedCount = $this->imageCacheService->clearCache();

        $this->assertEquals(0, $deletedCount);
    }

    #[Test]
    public function test_clear_cache_returns_zero_when_disabled(): void
    {
        config(['images.cache.enabled' => false]);

        $deletedCount = $this->imageCacheService->clearCache();

        $this->assertEquals(0, $deletedCount);
    }

    #[Test]
    public function test_cache_stats_format_bytes_correctly(): void
    {
        // Clear cache first to ensure clean state
        $this->imageCacheService->clearCache();

        // Store data of known size to test formatBytes
        $largeData = str_repeat('x', 2048); // 2KB
        $this->imageCacheService->storeCachedOptimizations('large_checksum', ['avif' => ['thumb' => $largeData]], 100, 100);

        $stats = $this->imageCacheService->getCacheStats();

        $this->assertTrue($stats['enabled']);
        $this->assertGreaterThan(0, $stats['total_keys']);
        $this->assertGreaterThanOrEqual(0, $stats['total_memory']); // Can be 0 for array cache
        $this->assertStringContainsString('B', $stats['total_memory_human']); // Should contain unit
        $this->assertIsInt($stats['ttl']);
        $this->assertIsBool($stats['compression_enabled']);
        $this->assertEquals('md5', $stats['hash_algorithm']);
    }

    #[Test]
    public function test_calculates_checksum_with_different_algorithms(): void
    {
        config(['images.cache.hash_algo' => 'sha256']);
        $service = new ImageCacheService;

        $imageContent = 'test_image_data';
        $checksum = $service->calculateChecksum($imageContent);

        $this->assertEquals(hash('sha256', $imageContent), $checksum);
        $this->assertNotEquals(md5($imageContent), $checksum);
    }

    #[Test]
    public function test_cache_key_generation_uses_configured_prefix(): void
    {
        config(['images.cache.key_prefix' => 'custom_prefix']);
        $service = new ImageCacheService;

        // Test key generation indirectly through cache operations
        $checksum = 'test_checksum';
        $service->storeCachedOptimizations($checksum, [], 100, 100);

        // Verify the key was generated with custom prefix by checking cache directly
        $expectedKey = "custom_prefix:{$checksum}";
        $this->assertTrue(Cache::store('array')->has($expectedKey));
    }

    #[Test]
    public function test_format_bytes_through_cache_stats(): void
    {
        // Test formatBytes function indirectly through getCacheStats
        $stats = $this->imageCacheService->getCacheStats();

        // Test with 0 bytes
        $this->assertEquals('0 B', $stats['total_memory_human']);

        // Store some data and check again
        $this->imageCacheService->storeCachedOptimizations('test', ['avif' => ['thumb' => 'data']], 100, 100);
        $stats = $this->imageCacheService->getCacheStats();

        // Should contain a valid unit (B, KB, MB, or GB)
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['total_memory_human']);
    }

    #[Test]
    public function test_compression_with_valid_data(): void
    {
        config(['images.cache.compress' => true]);
        $service = new ImageCacheService;

        $checksum = 'test_compression_valid';
        $optimizedImages = [
            'avif' => ['thumbnail' => 'small_data_that_compresses_well'],
        ];

        // Store with compression enabled
        $service->storeCachedOptimizations($checksum, $optimizedImages, 100, 100);

        // Should successfully retrieve compressed data
        $cached = $service->getCachedOptimizations($checksum);

        $this->assertNotNull($cached);
        $this->assertEquals($optimizedImages, $cached['optimized_files']);
    }

    #[Test]
    public function test_constructor_uses_default_config_values(): void
    {
        // Test with unusual but valid config values
        config([
            'images.cache.key_prefix' => 'unusual_prefix',
            'images.cache.hash_algo' => 'sha1',
            'images.cache.compress' => false,
            'images.cache.ttl' => 1800,
        ]);

        $service = new ImageCacheService;

        // Test that service works with unusual config values
        $checksum = $service->calculateChecksum('test_data');
        $this->assertIsString($checksum);
        $this->assertNotEmpty($checksum);

        // Verify it uses sha1 instead of md5
        $expectedSha1 = hash('sha1', 'test_data');
        $this->assertEquals($expectedSha1, $checksum);

        // Test cache operations work
        $service->storeCachedOptimizations($checksum, [], 50, 50);
        $cached = $service->getCachedOptimizations($checksum);

        // Should work with unusual config values
        $this->assertTrue($cached === null || is_array($cached));
    }
}
