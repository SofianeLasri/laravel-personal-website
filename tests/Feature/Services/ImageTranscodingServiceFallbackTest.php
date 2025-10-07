<?php

namespace Tests\Feature\Services;

use App\Enums\ImageTranscodingError;
use App\Exceptions\ImageTranscodingException;
use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageTranscodingServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected ImageTranscodingService $imageTranscodingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('image.drivers', ['imagick', 'gd']);
        Config::set('image.fallback.enabled', true);
        Config::set('image.format_support.imagick', ['avif', 'webp', 'jpeg', 'png']);
        Config::set('image.format_support.gd', ['webp', 'jpeg', 'png']);

        $this->imageTranscodingService = app(ImageTranscodingService::class);
    }

    #[Test]
    public function it_detects_available_drivers()
    {
        $availableDrivers = $this->imageTranscodingService->getAvailableDrivers();

        $this->assertIsArray($availableDrivers);
        $this->assertNotEmpty($availableDrivers);

        // At least one driver should be available
        $this->assertTrue(
            in_array('imagick', $availableDrivers) || in_array('gd', $availableDrivers),
            'Either Imagick or GD should be available'
        );
    }

    #[Test]
    public function it_checks_fallback_availability()
    {
        $isFallbackAvailable = $this->imageTranscodingService->isFallbackAvailable();

        // This depends on which drivers are actually available on the system
        $this->assertIsBool($isFallbackAvailable);
    }

    #[Test]
    public function it_can_get_image_dimensions()
    {
        // Create a simple 1x1 pixel PNG image
        $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAHGPh6kLwAAAABJRU5ErkJggg==');

        $dimensions = $this->imageTranscodingService->getDimensions($imageData);

        $this->assertIsArray($dimensions);
        $this->assertArrayHasKey('width', $dimensions);
        $this->assertArrayHasKey('height', $dimensions);
        $this->assertEquals(1, $dimensions['width']);
        $this->assertEquals(1, $dimensions['height']);
    }

    #[Test]
    public function it_handles_invalid_image_data()
    {
        $this->expectException(ImageTranscodingException::class);

        $invalidImageData = 'this is not image data';
        $this->imageTranscodingService->transcode($invalidImageData, null, 'jpeg');
    }

    #[Test]
    public function exception_contains_proper_error_information()
    {
        try {
            $invalidImageData = 'this is not image data';
            $this->imageTranscodingService->transcode($invalidImageData, null, 'jpeg');
        } catch (ImageTranscodingException $e) {
            $this->assertInstanceOf(ImageTranscodingError::class, $e->getErrorCode());
            $this->assertIsString($e->getDriverUsed());
            $this->assertIsArray($e->getContext());
            $this->assertIsArray($e->toArray());

            // Test severity levels
            $this->assertContains($e->getSeverity(), ['info', 'warning', 'error', 'critical']);
        }
    }
}
