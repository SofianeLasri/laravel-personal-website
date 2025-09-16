<?php

namespace Tests\Feature\Models;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Services\ImageCacheService;
use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PictureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Enable cache for testing
        config([
            'images.cache.enabled' => true,
            'images.cache.driver' => 'array',
            'images.cache.ttl' => 3600,
            'images.cache.hash_algo' => 'md5',
            'images.cache.compress' => false,
            'images.cache.key_prefix' => 'test_image_cache',
        ]);
    }

    #[Test]
    public function test_picture_optimization_with_cache_hit(): void
    {
        // Create a picture
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
            'width' => null,
            'height' => null,
        ]);

        // Create fake original image
        $originalImageContent = 'fake_original_image_content';
        Storage::disk('public')->put($picture->path_original, $originalImageContent);

        // Pre-populate cache with optimized data
        $checksum = md5($originalImageContent);
        $cachedData = [
            'width' => 1920,
            'height' => 1080,
            'cached_at' => now()->toISOString(),
            'optimized_files' => [
                'avif' => [
                    'thumbnail' => 'cached_avif_thumbnail_data',
                    'small' => 'cached_avif_small_data',
                    'medium' => 'cached_avif_medium_data',
                    'large' => 'cached_avif_large_data',
                    'full' => 'cached_avif_full_data',
                ],
                'webp' => [
                    'thumbnail' => 'cached_webp_thumbnail_data',
                    'small' => 'cached_webp_small_data',
                    'medium' => 'cached_webp_medium_data',
                    'large' => 'cached_webp_large_data',
                    'full' => 'cached_webp_full_data',
                ],
                'jpg' => [
                    'thumbnail' => 'cached_jpg_thumbnail_data',
                    'small' => 'cached_jpg_small_data',
                    'medium' => 'cached_jpg_medium_data',
                    'large' => 'cached_jpg_large_data',
                    'full' => 'cached_jpg_full_data',
                ],
            ],
        ];

        $imageCacheService = app(ImageCacheService::class);
        $cacheKey = "test_image_cache:{$checksum}";
        Cache::store('array')->put($cacheKey, json_encode($cachedData), 3600);

        // Optimize the picture
        $picture->optimize();

        // Assert picture dimensions were updated
        $picture->refresh();
        $this->assertEquals(1920, $picture->width);
        $this->assertEquals(1080, $picture->height);

        // Assert optimized pictures were created
        $this->assertDatabaseHas('optimized_pictures', [
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
            'path' => 'uploads/test-image_thumbnail.avif',
        ]);

        $this->assertDatabaseHas('optimized_pictures', [
            'picture_id' => $picture->id,
            'variant' => 'small',
            'format' => 'webp',
            'path' => 'uploads/test-image_small.webp',
        ]);

        // Assert files were created in storage
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_thumbnail.avif'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_small.webp'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image_full.jpg'));

        // Assert file contents match cached data
        $this->assertEquals('cached_avif_thumbnail_data', Storage::disk('public')->get('uploads/test-image_thumbnail.avif'));
        $this->assertEquals('cached_webp_small_data', Storage::disk('public')->get('uploads/test-image_small.webp'));

        // Count total optimized pictures (3 formats × 5 variants = 15)
        $this->assertEquals(15, $picture->optimizedPictures()->count());
    }

    #[Test]
    public function test_picture_optimization_with_cache_miss_stores_in_cache(): void
    {
        // Mock the ImageTranscodingService
        $mockTranscodingService = $this->createMock(ImageTranscodingService::class);
        $mockTranscodingService->method('getDimensions')
            ->willReturn(['width' => 800, 'height' => 600]);

        $mockTranscodingService->method('transcode')
            ->willReturn('mocked_optimized_image_data');

        $this->app->instance(ImageTranscodingService::class, $mockTranscodingService);

        // Create a picture
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
            'width' => null,
            'height' => null,
        ]);

        $originalImageContent = 'fake_original_image_content';
        Storage::disk('public')->put($picture->path_original, $originalImageContent);

        // Ensure cache is empty
        $checksum = md5($originalImageContent);
        $cacheKey = "test_image_cache:{$checksum}";
        Cache::store('array')->forget($cacheKey);

        // Optimize the picture
        $picture->optimize();

        // Assert picture dimensions were updated
        $picture->refresh();
        $this->assertEquals(800, $picture->width);
        $this->assertEquals(600, $picture->height);

        // Assert optimized pictures were created
        $this->assertGreaterThan(0, $picture->optimizedPictures()->count());

        // Assert data was stored in cache
        $cachedData = Cache::store('array')->get($cacheKey);
        $this->assertNotNull($cachedData);

        $decodedData = json_decode($cachedData, true);
        $this->assertEquals(800, $decodedData['width']);
        $this->assertEquals(600, $decodedData['height']);
        $this->assertArrayHasKey('optimized_files', $decodedData);
        $this->assertArrayHasKey('cached_at', $decodedData);
    }

    #[Test]
    public function test_picture_optimization_with_cache_disabled(): void
    {
        // Disable cache
        config(['images.cache.enabled' => false]);

        // Mock the ImageTranscodingService
        $mockTranscodingService = $this->createMock(ImageTranscodingService::class);
        $mockTranscodingService->method('getDimensions')
            ->willReturn(['width' => 800, 'height' => 600]);

        $mockTranscodingService->method('transcode')
            ->willReturn('mocked_optimized_image_data');

        $this->app->instance(ImageTranscodingService::class, $mockTranscodingService);

        // Create a picture
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
            'width' => null,
            'height' => null,
        ]);

        $originalImageContent = 'fake_original_image_content';
        Storage::disk('public')->put($picture->path_original, $originalImageContent);

        // Optimize the picture
        $picture->optimize();

        // Assert picture dimensions were updated
        $picture->refresh();
        $this->assertEquals(800, $picture->width);
        $this->assertEquals(600, $picture->height);

        // Assert optimized pictures were created
        $this->assertGreaterThan(0, $picture->optimizedPictures()->count());

        // Assert nothing was stored in cache
        $checksum = md5($originalImageContent);
        $cacheKey = "test_image_cache:{$checksum}";
        $cachedData = Cache::store('array')->get($cacheKey);
        $this->assertNull($cachedData);
    }

    #[Test]
    public function test_picture_optimization_with_invalid_path(): void
    {
        // Create a picture with no path, avoiding factory defaults
        $picture = Picture::factory()->make([
            'path_original' => null,
            'width' => null,
            'height' => null,
        ]);
        $picture->save();

        // Capture log messages
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture optimization failed: path_original is empty');

        // Optimize the picture
        $picture->optimize();

        // Assert no optimized pictures were created
        $this->assertEquals(0, $picture->optimizedPictures()->count());

        // Assert dimensions were not updated
        $picture->refresh();
        $this->assertNull($picture->width);
        $this->assertNull($picture->height);
    }

    #[Test]
    public function test_picture_optimization_with_missing_file(): void
    {
        // Create a picture with a path that doesn't exist, avoiding factory defaults
        $picture = Picture::factory()->make([
            'path_original' => 'uploads/non-existent.jpg',
            'width' => null,
            'height' => null,
        ]);
        $picture->save();

        // Capture log messages
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture optimization failed: file does not exist', [
                'path' => 'uploads/non-existent.jpg',
            ]);

        // Optimize the picture
        $picture->optimize();

        // Assert no optimized pictures were created
        $this->assertEquals(0, $picture->optimizedPictures()->count());

        // Assert dimensions were not updated
        $picture->refresh();
        $this->assertNull($picture->width);
        $this->assertNull($picture->height);
    }

    #[Test]
    public function test_picture_optimization_with_cache_copy_failure(): void
    {
        // Mock ImageCacheService to simulate copy failure
        $mockCacheService = $this->createMock(ImageCacheService::class);
        $mockCacheService->method('calculateChecksum')
            ->willReturn('test_checksum');

        $mockCacheService->method('getCachedOptimizations')
            ->willReturn([
                'width' => 1920,
                'height' => 1080,
                'optimized_files' => ['avif' => ['thumbnail' => 'cached_data']],
            ]);

        // Simulate copy failure
        $mockCacheService->method('copyCachedFiles')
            ->willReturn(false);

        // Also mock ImageTranscodingService for fallback
        $mockTranscodingService = $this->createMock(ImageTranscodingService::class);
        $mockTranscodingService->method('getDimensions')
            ->willReturn(['width' => 800, 'height' => 600]);

        $mockTranscodingService->method('transcode')
            ->willReturn('mocked_optimized_image_data');

        $this->app->instance(ImageCacheService::class, $mockCacheService);
        $this->app->instance(ImageTranscodingService::class, $mockTranscodingService);

        // Create a picture
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test-image.jpg',
        ]);

        $originalImageContent = 'fake_original_image_content';
        Storage::disk('public')->put($picture->path_original, $originalImageContent);

        // Optimize the picture
        $picture->optimize();

        // Assert fallback optimization occurred
        $picture->refresh();
        $this->assertEquals(800, $picture->width);
        $this->assertEquals(600, $picture->height);
        $this->assertGreaterThan(0, $picture->optimizedPictures()->count());
    }

    #[Test]
    public function test_get_optimized_picture_method(): void
    {
        $picture = Picture::factory()->create();

        // Create some optimized pictures
        $optimizedPicture = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
            'path' => 'uploads/test_thumbnail.avif',
        ]);

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'small',
            'format' => 'webp',
            'path' => 'uploads/test_small.webp',
        ]);

        // Test getting specific optimized picture
        $result = $picture->getOptimizedPicture('thumbnail', 'avif');
        $this->assertNotNull($result);
        $this->assertEquals($optimizedPicture->id, $result->id);

        // Test getting non-existent combination
        $result = $picture->getOptimizedPicture('large', 'avif');
        $this->assertNull($result);
    }

    #[Test]
    public function test_get_url_method(): void
    {
        $picture = Picture::factory()->create();

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'avif',
            'path' => 'uploads/test_thumbnail.avif',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('uploads/test_thumbnail.avif', 'fake_content');

        $url = $picture->getUrl('thumbnail', 'avif');
        $this->assertStringContainsString('test_thumbnail.avif', $url);

        // Test non-existent optimized picture
        $emptyUrl = $picture->getUrl('nonexistent', 'avif');
        $this->assertEquals('', $emptyUrl);
    }
}
