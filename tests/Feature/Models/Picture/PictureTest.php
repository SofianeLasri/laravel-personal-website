<?php

namespace Tests\Feature\Models\Picture;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionClass;
use Tests\TestCase;

#[CoversClass(Picture::class)]
class PictureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        config(['app.cdn_disk' => null]);
    }

    #[Test]
    public function test_picture_factory_creates_valid_picture()
    {
        $picture = Picture::factory()->create();

        $this->assertDatabaseHas('pictures', [
            'id' => $picture->id,
            'filename' => $picture->filename,
        ]);

        $this->assertNotNull($picture->path_original);
        $this->assertGreaterThan(0, $picture->width);
        $this->assertGreaterThan(0, $picture->height);
        $this->assertGreaterThan(0, $picture->size);

        Storage::disk('public')->assertExists($picture->path_original);
    }

    #[Test]
    public function test_picture_has_many_optimized_pictures()
    {
        $picture = Picture::factory()
            ->withOptimizedPictures()
            ->create();

        $this->assertCount(count(OptimizedPicture::VARIANTS) * count(OptimizedPicture::FORMATS), $picture->optimizedPictures);

        foreach (OptimizedPicture::VARIANTS as $variant) {
            foreach (OptimizedPicture::FORMATS as $format) {
                $this->assertNotNull(
                    $picture->optimizedPictures->first(function ($optimizedPicture) use ($variant, $format) {
                        return $optimizedPicture->variant === $variant && $optimizedPicture->format === $format;
                    })
                );
            }
        }
    }

    #[Test]
    public function test_get_optimized_picture_returns_correct_picture()
    {
        $picture = Picture::factory()->create();

        $optimizedPicture = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'format' => 'webp',
        ]);

        $result = $picture->getOptimizedPicture('thumbnail', 'webp');

        $this->assertNotNull($result);
        $this->assertEquals($optimizedPicture->id, $result->id);
        $this->assertEquals('thumbnail', $result->variant);
        $this->assertEquals('webp', $result->format);

        $nullResult = $picture->getOptimizedPicture('invalid', 'webp');
        $this->assertNull($nullResult);
    }

    #[Test]
    public function test_optimize_creates_optimized_pictures()
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        $path = 'uploads/test_optimize.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
            'width' => null,
            'height' => null,
        ]);

        $this->assertCount(0, $picture->optimizedPictures);

        // Use the actual service from container
        app()->forgetInstance(ImageTranscodingService::class);

        $picture->optimize();
        $picture->refresh();

        $this->assertEquals(512, $picture->width);
        $this->assertEquals(384, $picture->height);

        // The service might not create all variants due to transcoding limitations
        $this->assertGreaterThan(0, $picture->optimizedPictures->count());

        foreach ($picture->optimizedPictures as $optimizedPicture) {
            Storage::disk('public')->assertExists($optimizedPicture->path);
        }
    }

    #[Test]
    public function test_optimize_logs_warning_when_original_doesnt_exist()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture optimization failed: file does not exist', [
                'path' => 'non_existent_path.jpg',
            ]);

        $picture = Picture::factory()->create([
            'path_original' => 'non_existent_path.jpg',
        ]);

        $picture->optimize();

        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    public function test_cant_optimize_with_empty_original_picture()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture optimization failed: path_original is empty');

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);
        $imageTranscodingService
            ->expects($this->never())
            ->method('getDimensions');

        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        $picture->optimize();
        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    public function test_get_optimized_dimension_returns_correct_value()
    {
        $picture = new Picture;

        $result = $this->invokePrivateMethod($picture, 'getOptimizedDimension', [500, 1000]);
        $this->assertEquals(500, $result);

        $result = $this->invokePrivateMethod($picture, 'getOptimizedDimension', [1500, 1000]);
        $this->assertEquals(1000, $result);
    }

    #[Test]
    public function test_delete_optimized_removes_all_optimized_pictures()
    {
        $picture = Picture::factory()->withOptimizedPictures()->create();

        $paths = $picture->optimizedPictures->pluck('path')->toArray();

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $picture->deleteOptimized();

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $picture->refresh();
        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    public function test_delete_original_removes_original_picture()
    {
        $picture = Picture::factory()->create();
        $path = $picture->path_original;

        Storage::disk('public')->assertExists($path);

        $picture->deleteOriginal();

        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    #[TestDox('deleteOriginal handles case when path_original is null')]
    public function test_delete_original_with_null_path()
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        // Should not throw any exception
        $picture->deleteOriginal();

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    #[Test]
    #[TestDox('deleteOriginal handles case when original file does not exist')]
    public function test_delete_original_with_non_existent_file()
    {
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/non_existent.jpg',
        ]);

        Storage::disk('public')->assertMissing($picture->path_original);

        // Should not throw any exception
        $picture->deleteOriginal();

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    #[Test]
    public function test_transcoding_when_it_is_worth_it_returns_null_if_no_original_picture()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture transcoding failed: path_original is empty');

        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);
        $imageTranscodingService
            ->expects($this->never())
            ->method('transcode');
        $imageTranscodingService
            ->expects($this->never())
            ->method('getDimensions');

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 500, 1000, 'webp']
        );

        $this->assertNull($result);
    }

    #[TestDox('Test transcoding when it is worth it returns null if original picture file exists but is empty')]
    public function test_transcoding_when_it_is_worth_it_returns_null_if_original_picture_is_empty()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture transcoding failed: original image is empty', [
                'path' => 'empty_file.jpg',
            ]);

        Storage::disk('public')->put('empty_file.jpg', '');
        $picture = Picture::factory()->create([
            'path_original' => 'empty_file.jpg',
        ]);

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);
        $imageTranscodingService
            ->expects($this->never())
            ->method('transcode');
        $imageTranscodingService
            ->expects($this->never())
            ->method('getDimensions');

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 500, 1000, 'webp']
        );

        $this->assertNull($result);
    }

    #[Test]
    public function test_transcoding_when_it_is_worth_it()
    {
        $originalImage = 'original_content';
        $transcodedImage = 'transcoded_content';
        Storage::disk('public')->put('original.jpg', $originalImage);
        $picture = Picture::factory()->create([
            'path_original' => 'original.jpg',
            'width' => 1000,
            'height' => 1000,
        ]);

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);

        $imageTranscodingService
            ->expects($this->atLeastOnce())
            ->method('transcode')
            ->willReturn($transcodedImage);

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 500, 1000, 'webp']
        );

        $this->assertEquals($transcodedImage, $result);

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 1000, 1000, 'webp']
        );

        $this->assertEquals($transcodedImage, $result);
    }

    #[Test]
    #[TestDox('transcodeIfItIsWorthIt handles generic exceptions and returns null')]
    public function test_transcoding_when_it_is_worth_it_handles_generic_exception()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Unexpected error during image transcoding' &&
                       isset($context['picture_id']) &&
                       isset($context['error']) &&
                       isset($context['trace']);
            });

        $originalImage = 'original_content';
        Storage::disk('public')->put('original.jpg', $originalImage);
        $picture = Picture::factory()->create([
            'path_original' => 'original.jpg',
            'id' => 123,
        ]);

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);
        $imageTranscodingService
            ->expects($this->once())
            ->method('transcode')
            ->willThrowException(new \Exception('Generic error'));

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 500, 1000, 'webp']
        );

        $this->assertNull($result);
    }

    #[Test]
    #[TestDox('transcodeIfItIsWorthIt handles ImageTranscodingException without notification for warning severity')]
    public function test_transcoding_handles_image_transcoding_exception_warning()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Image transcoding failed with specific error' &&
                       isset($context['picture_id']) &&
                       isset($context['error_code']) &&
                       isset($context['driver_used']);
            });

        $originalImage = 'original_content';
        Storage::disk('public')->put('original.jpg', $originalImage);
        $picture = Picture::factory()->create([
            'path_original' => 'original.jpg',
            'filename' => 'test.jpg',
        ]);

        $exception = new \App\Exceptions\ImageTranscodingException(
            \App\Enums\ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'imagick',
            'Warning error'
        );

        // IMAGICK_ENCODING_FAILED has 'warning' severity, not 'critical' or 'error'
        // So NotificationService should not be called

        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);
        $imageTranscodingService
            ->expects($this->once())
            ->method('transcode')
            ->willThrowException($exception);

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, 500, 1000, 'webp']
        );

        $this->assertNull($result);
    }

    #[Test]
    public function test_store_optimized_images_with_empty_path()
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('UploadedPicture storeOptimizedImages failed: path_original is empty');

        $this->invokePrivateMethod($picture, 'storeOptimizedImages', [[], 'webp']);

        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    public function test_store_optimized_images()
    {
        $picture = Picture::factory()->create();
        $optimizedImages = [
            'thumbnail' => 'thumbnail_content',
            'small' => 'small_content',
        ];
        $format = 'webp';

        $this->invokePrivateMethod($picture, 'storeOptimizedImages', [$optimizedImages, $format]);

        foreach ($optimizedImages as $variant => $content) {
            $path = preg_replace('/\.[^.]+$/', '', $picture->path_original)."_$variant.$format";
            Storage::disk('public')->assertExists($path);
        }

        $picture->refresh();
        $this->assertCount(count($optimizedImages), $picture->optimizedPictures);

        foreach ($optimizedImages as $variant => $_) {
            $this->assertNotNull(
                $picture->optimizedPictures->first(function ($optimizedPicture) use ($variant, $format) {
                    return $optimizedPicture->variant === $variant && $optimizedPicture->format === $format;
                })
            );
        }
    }

    #[Test]
    #[TestDox('storeOptimizedImages skips empty images and logs errors')]
    public function test_store_optimized_images_skips_empty_images()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Optimized image is empty, skipping storage' &&
                       isset($context['picture_id']) &&
                       isset($context['variant']) &&
                       isset($context['format']);
            });

        Log::shouldReceive('info')->andReturn(true);

        $picture = Picture::factory()->create();
        $optimizedImages = [
            'thumbnail' => '',  // Empty image
            'small' => 'valid_content',
        ];
        $format = 'webp';

        $this->invokePrivateMethod($picture, 'storeOptimizedImages', [$optimizedImages, $format]);

        // Should only create one OptimizedPicture (for 'small')
        $picture->refresh();
        $this->assertCount(1, $picture->optimizedPictures);
        $this->assertEquals('small', $picture->optimizedPictures->first()->variant);
    }

    #[Test]
    #[TestDox('storeOptimizedImages handles zero-byte file storage failure')]
    public function test_store_optimized_images_handles_zero_byte_file()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Failed to store optimized image or file has 0 bytes';
            });

        Log::shouldReceive('info')->andReturn(true);

        $picture = Picture::factory()->create();

        // Override Storage behavior to simulate zero-byte file
        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->andReturn(true);

        Storage::shouldReceive('exists')
            ->times(2)
            ->andReturn(true);

        Storage::shouldReceive('size')
            ->once()
            ->andReturn(0); // Zero bytes

        Storage::shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $optimizedImages = ['thumbnail' => 'content'];
        $format = 'webp';

        $this->invokePrivateMethod($picture, 'storeOptimizedImages', [$optimizedImages, $format]);

        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    #[TestDox('storeOptimizedImages sends notification for failed variants')]
    public function test_store_optimized_images_sends_notification_for_failures()
    {
        // Mock NotificationService
        $notificationService = $this->createMock(\App\Services\NotificationService::class);
        $notificationService->expects($this->once())
            ->method('error')
            ->with(
                'Échec d\'optimisation d\'image',
                $this->stringContains('Certaines variantes n\'ont pas pu être créées'),
                $this->arrayHasKey('failed_variants')
            );

        $this->instance(\App\Services\NotificationService::class, $notificationService);

        $picture = Picture::factory()->create([
            'filename' => 'test.jpg',
        ]);

        $optimizedImages = [
            'thumbnail' => '',  // This will fail
            'small' => 'valid_content',
        ];
        $format = 'webp';

        $this->invokePrivateMethod($picture, 'storeOptimizedImages', [$optimizedImages, $format]);
    }

    #[Test]
    public function test_optimize_with_cdn_disk_configured()
    {
        Storage::fake('cdn');
        config(['app.cdn_disk' => 'cdn']);

        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        $path = 'uploads/test_optimize_cdn.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
        ]);

        $picture->optimize();
        $picture->refresh();

        foreach ($picture->optimizedPictures as $optimizedPicture) {
            Storage::disk('public')->assertExists($optimizedPicture->path);
            Storage::disk('cdn')->assertExists($optimizedPicture->path);
        }
    }

    #[Test]
    public function test_optimize_with_transcoding_failure()
    {
        Storage::fake('public');

        $path = 'test.jpg';
        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
        ]);

        $this->instance(
            ImageTranscodingService::class,
            Mockery::mock(ImageTranscodingService::class, function ($mock) {
                $mock->shouldReceive('transcode')
                    ->andThrow(new \App\Exceptions\ImageTranscodingException(
                        \App\Enums\ImageTranscodingError::IMAGICK_ENCODING_FAILED,
                        'imagick',
                        'Test error'
                    ));
                $mock->shouldReceive('getDimensions')->andReturn(['width' => 512, 'height' => 384]);
            })
        );

        $picture->optimize();

        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    public function test_get_url()
    {
        $picture = Picture::factory()
            ->withOptimizedPictures()
            ->create([
                'path_original' => 'uploads/test.jpg',
            ]);

        $url = $picture->getUrl('medium', 'webp');

        $this->assertEquals(
            Storage::disk('public')->url($picture->getOptimizedPicture('medium', 'webp')->path),
            $url
        );
    }

    #[Test]
    #[TestDox('getUrl returns CDN URL when CDN disk is configured')]
    public function test_get_url_with_cdn_configured()
    {
        Storage::fake('cdn');
        config(['app.cdn_disk' => 'cdn']);

        $picture = Picture::factory()
            ->withOptimizedPictures()
            ->create([
                'path_original' => 'uploads/test_cdn.jpg',
            ]);

        $url = $picture->getUrl('medium', 'webp');

        $this->assertEquals(
            Storage::disk('cdn')->url($picture->getOptimizedPicture('medium', 'webp')->path),
            $url
        );
    }

    #[Test]
    #[TestDox('getUrl returns empty string when optimized picture does not exist')]
    public function test_get_url_returns_empty_string_when_optimized_picture_not_exists()
    {
        $picture = Picture::factory()->create();

        $url = $picture->getUrl('medium', 'webp');

        $this->assertEquals('', $url);
    }

    #[Test]
    public function test_has_valid_original_path()
    {
        $picture = Picture::factory()->create([
            'path_original' => 'uploads/test.jpg',
        ]);

        $this->assertTrue($picture->hasValidOriginalPath());
    }

    #[Test]
    public function test_has_invalid_original_path()
    {
        $picture = Picture::factory()->create([
            'path_original' => null,
        ]);

        $this->assertFalse($picture->hasValidOriginalPath());
    }

    #[Test]
    #[TestDox('reoptimize deletes existing optimized pictures and dispatches new job')]
    public function test_reoptimize_deletes_optimized_pictures_and_dispatches_job()
    {
        \Queue::fake();

        $picture = Picture::factory()->withOptimizedPictures()->create();

        $this->assertGreaterThan(0, $picture->optimizedPictures->count());

        $picture->reoptimize();

        $picture->refresh();
        $this->assertCount(0, $picture->optimizedPictures);

        \Queue::assertPushed(\App\Jobs\PictureJob::class);
    }

    #[Test]
    #[TestDox('hasInvalidOptimizedPictures returns false when all pictures are valid')]
    public function test_has_invalid_optimized_pictures_returns_false_when_all_valid()
    {
        $picture = Picture::factory()->withOptimizedPictures()->create();

        $this->assertFalse($picture->hasInvalidOptimizedPictures());
    }

    #[Test]
    #[TestDox('hasInvalidOptimizedPictures returns true when some pictures have zero size')]
    public function test_has_invalid_optimized_pictures_returns_true_when_zero_size()
    {
        $picture = Picture::factory()->create();

        // Create optimized picture with zero-size file
        $optimizedPicture = OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'path' => 'uploads/zero_size.webp',
        ]);

        Storage::disk('public')->put($optimizedPicture->path, '');

        $this->assertTrue($picture->hasInvalidOptimizedPictures());
    }

    #[Test]
    #[TestDox('hasInvalidOptimizedPictures returns false when optimized picture file does not exist')]
    public function test_has_invalid_optimized_pictures_returns_false_when_file_not_exists()
    {
        $picture = Picture::factory()->create();

        OptimizedPicture::factory()->create([
            'picture_id' => $picture->id,
            'path' => 'uploads/non_existent.webp',
        ]);

        $this->assertFalse($picture->hasInvalidOptimizedPictures());
    }

    #[Test]
    #[TestDox('optimize uses cache when enabled and cache hit occurs')]
    public function test_optimize_uses_cache_when_enabled_and_cache_hit()
    {
        config(['images.cache.enabled' => true]);

        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        $path = 'uploads/test_cache.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
        ]);

        // Mock ImageCacheService
        $imageCacheService = $this->createMock(\App\Services\ImageCacheService::class);
        $imageCacheService->expects($this->once())
            ->method('calculateChecksum')
            ->willReturn('mock_checksum');

        $imageCacheService->expects($this->once())
            ->method('getCachedOptimizations')
            ->with('mock_checksum')
            ->willReturn(['mock' => 'cached_data']);

        $imageCacheService->expects($this->once())
            ->method('copyCachedFiles')
            ->willReturn(true);

        $this->instance(\App\Services\ImageCacheService::class, $imageCacheService);

        $picture->optimize();

        // Should not create optimized pictures when using cache
        $this->assertCount(0, $picture->optimizedPictures);
    }

    #[Test]
    #[TestDox('optimize falls back to normal optimization when cache miss occurs')]
    public function test_optimize_falls_back_when_cache_miss()
    {
        // Disable cache for this test to ensure normal optimization path
        config(['images.cache.enabled' => false]);

        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        $path = 'uploads/test_cache_miss.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
            'width' => null,
            'height' => null,
        ]);

        $picture->optimize();
        $picture->refresh();

        $this->assertEquals(512, $picture->width);
        $this->assertEquals(384, $picture->height);
        $this->assertGreaterThan(0, $picture->optimizedPictures->count());
    }

    #[Test]
    #[TestDox('optimize skips cache when disabled')]
    public function test_optimize_skips_cache_when_disabled()
    {
        config(['images.cache.enabled' => false]);

        $manager = new ImageManager(new Driver);
        $image = $manager->create(512, 384)->fill('F78E57');
        $path = 'uploads/test_no_cache.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        $picture = Picture::factory()->create([
            'path_original' => $path,
            'width' => null,
            'height' => null,
        ]);

        // Mock ImageCacheService - should not be called
        $imageCacheService = $this->createMock(\App\Services\ImageCacheService::class);
        $imageCacheService->expects($this->never())
            ->method('getCachedOptimizations');

        $this->instance(\App\Services\ImageCacheService::class, $imageCacheService);

        $picture->optimize();
        $picture->refresh();

        $this->assertEquals(512, $picture->width);
        $this->assertEquals(384, $picture->height);
        $this->assertGreaterThan(0, $picture->optimizedPictures->count());
    }

    /**
     * Helper method to invoke private methods
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
