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
}
