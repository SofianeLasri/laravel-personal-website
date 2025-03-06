<?php

namespace Tests\Feature\Services;

use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ImageTranscodingService::class)]
class ImageTranscodingServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws ImagickException
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.imagick.max_width', 1024);
        config()->set('app.imagick.max_height', 1024);

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, 1024 * 1024);
    }

    #[Test]
    public function test_it_can_transcode_a_webp_image()
    {
        $image = (new ImageManager(new Driver))->create(512, 512)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, 100, 'webp');

        $this->assertNotEmpty($transcodedImageContent);
        $this->assertNotEquals($image, $transcodedImageContent);
    }

    #[Test]
    public function test_it_can_transcode_a_jpeg_image()
    {
        $image = (new ImageManager(new Driver))->create(512, 512)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, 100, 'jpeg');

        $this->assertNotEmpty($transcodedImageContent);
        $this->assertNotEquals($image, $transcodedImageContent);
    }

    #[Test]
    public function test_it_can_transcode_a_png_image()
    {
        $image = (new ImageManager(new Driver))->create(512, 512)->fill('ccc')->toPng()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, 100, 'png');

        $this->assertNotEmpty($transcodedImageContent);
        $this->assertNotEquals($image, $transcodedImageContent);
    }

    #[Test]
    public function test_it_can_transcode_a_avif_image()
    {
        $image = (new ImageManager(new Driver))->create(512, 512)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, 100);

        $this->assertNotEmpty($transcodedImageContent);
        $this->assertNotEquals($image, $transcodedImageContent);
    }

    #[Test]
    public function test_it_can_transcode_an_image_without_resolution()
    {
        $image = (new ImageManager(new Driver))->create(512, 512)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, null, 'webp');

        $this->assertNotEmpty($transcodedImageContent);
        $this->assertNotEquals($image, $transcodedImageContent);
    }

    #[Test]
    public function test_it_returns_null_for_image_exceeding_max_resolution()
    {
        $image = (new ImageManager(new Driver))->create(2000, 2000)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);

        Log::shouldReceive('error')
            ->once()
            ->with('Image resolution exceeds maximum allowed resolution', [
                'image_height' => 2000,
                'image_width' => 2000,
                'image_surface' => 2000 * 2000,
                'max_width' => 1024,
                'max_height' => 1024,
                'max_surface' => 1024 * 1024,
            ]);

        $transcodedImageContent = $service->transcode($image, null, 'webp');
        $this->assertNull($transcodedImageContent);
        $this->assertTrue(config('app.imagick.max_width') < 2000, 'La largeur dépasse la limite maximale');
        $this->assertTrue(config('app.imagick.max_height') < 2000, 'La hauteur dépasse la limite maximale');
    }

    #[Test]
    public function test_it_handles_invalid_source_image()
    {
        $this->withoutExceptionHandling();
        $invalidImage = 'not-an-image-content';

        $service = new ImageTranscodingService(new Driver);

        $transcodedImage = null;
        try {
            $transcodedImage = $service->transcode($invalidImage);
        } catch (DecoderException $e) {
            $this->assertInstanceOf(DecoderException::class, $e);
        }

        $this->assertNull($transcodedImage);
    }

    #[Test]
    public function test_it_can_get_dimensions_of_an_image()
    {
        $image = (new ImageManager(new Driver))->create(512, 256)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $dimensions = $service->getDimensions($image);

        $this->assertEquals(['width' => 512, 'height' => 256], $dimensions);
    }

    #[Test]
    public function test_it_handles_large_but_acceptable_resolution()
    {
        $image = (new ImageManager(new Driver))->create(1024, 1024)->fill('ccc')->toJpeg()->toString();

        $service = new ImageTranscodingService(new Driver);
        $transcodedImageContent = $service->transcode($image, 512, 'webp');

        $this->assertNotEmpty($transcodedImageContent);

        $dimensions = $service->getDimensions($transcodedImageContent);
        $this->assertEquals(512, $dimensions['width']);
        $this->assertEquals(512, $dimensions['height']);

        $this->assertEquals(1, $dimensions['width'] / $dimensions['height']);
    }
}
