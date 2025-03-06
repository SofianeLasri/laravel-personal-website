<?php

namespace Tests\Feature\Models;

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

        $picture->optimize();
        $picture->refresh();

        $this->assertEquals(512, $picture->width);
        $this->assertEquals(384, $picture->height);

        $this->assertCount(count(OptimizedPicture::VARIANTS) * count(OptimizedPicture::FORMATS), $picture->optimizedPictures);

        foreach ($picture->optimizedPictures as $optimizedPicture) {
            Storage::disk('public')->assertExists($optimizedPicture->path);
        }

        Storage::disk('public')->assertMissing($path);
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
    public function test_transcoding_when_it_is_worth_it()
    {
        $picture = new Picture;
        $imageTranscodingService = $this->createMock(ImageTranscodingService::class);

        $originalImage = 'original_content';
        $transcodedImage = 'transcoded_content';

        $imageTranscodingService
            ->expects($this->once())
            ->method('transcode')
            ->with($originalImage, 500, 'webp')
            ->willReturn($transcodedImage);

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, $originalImage, 500, 1000, 'webp']
        );

        $this->assertEquals($transcodedImage, $result);

        $result = $this->invokePrivateMethod(
            $picture,
            'transcodeIfItIsWorthIt',
            [$imageTranscodingService, $originalImage, 1000, 1000, 'webp']
        );

        $this->assertEquals($originalImage, $result);
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
        $picture = Picture::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->with('UploadedPicture optimization failed: transcoding failed', [
                'path' => $picture->path_original,
            ]);

        $this->instance(
            ImageTranscodingService::class,
            Mockery::mock(ImageTranscodingService::class, function ($mock) {
                $mock->shouldReceive('transcode')->andReturnNull();
                $mock->shouldReceive('getDimensions')->andReturn(['width' => 512, 'height' => 384]);
            })
        );

        $picture->optimize();

        $this->assertCount(0, $picture->optimizedPictures);
    }

    /**
     * Helper method to invoke private methods
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
