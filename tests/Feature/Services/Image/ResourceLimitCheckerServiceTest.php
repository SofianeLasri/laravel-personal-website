<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Image;

use App\Exceptions\ImageTranscodingException;
use App\Services\Image\ResourceLimitCheckerService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ResourceLimitCheckerService::class)]
class ResourceLimitCheckerServiceTest extends TestCase
{
    private ResourceLimitCheckerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ResourceLimitCheckerService::class);
    }

    #[Test]
    public function it_gets_configured_limits(): void
    {
        config(['app.imagick.max_width' => 10000]);
        config(['app.imagick.max_height' => 10000]);
        config(['image.gd.max_width' => 8000]);
        config(['image.gd.max_height' => 8000]);

        $limits = $this->service->getConfiguredLimits();

        $this->assertArrayHasKey('imagick', $limits);
        $this->assertArrayHasKey('gd', $limits);
        $this->assertEquals(10000, $limits['imagick']['max_width']);
        $this->assertEquals(10000, $limits['imagick']['max_height']);
        $this->assertEquals(8000, $limits['gd']['max_width']);
        $this->assertEquals(8000, $limits['gd']['max_height']);
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function it_gets_imagick_limits(): void
    {
        $limits = $this->service->getImagickLimits();

        $this->assertArrayHasKey('area', $limits);
        $this->assertArrayHasKey('memory', $limits);
        $this->assertArrayHasKey('disk', $limits);
        $this->assertIsNumeric($limits['area']);
        $this->assertIsNumeric($limits['memory']);
        $this->assertIsNumeric($limits['disk']);
    }

    #[Test]
    public function it_returns_empty_array_for_imagick_limits_when_extension_not_loaded(): void
    {
        if (extension_loaded('imagick')) {
            $this->markTestSkipped('This test requires imagick extension to NOT be loaded');
        }

        $limits = $this->service->getImagickLimits();

        $this->assertEmpty($limits);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_passes_check_for_small_image_with_gd(): void
    {
        config(['image.gd.max_width' => 8000]);
        config(['image.gd.max_height' => 8000]);

        // Create a small test image
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        // Should not throw an exception
        $this->service->checkGd($imageData);

        $this->assertTrue(true);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_throws_exception_for_oversized_image_with_gd(): void
    {
        config(['image.gd.max_width' => 50]);
        config(['image.gd.max_height' => 50]);

        // Create a test image larger than limits
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->expectException(ImageTranscodingException::class);

        $this->service->checkGd($imageData);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_throws_exception_for_invalid_image_data_with_gd(): void
    {
        $this->expectException(ImageTranscodingException::class);

        $this->service->checkGd('invalid image data');
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function it_passes_check_for_small_image_with_imagick(): void
    {
        config(['app.imagick.max_width' => 10000]);
        config(['app.imagick.max_height' => 10000]);
        config(['image.imagick.check_resource_limits' => true]);

        // Create a small test image using GD (for creating test data)
        if (extension_loaded('gd')) {
            $image = imagecreatetruecolor(100, 100);
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);
        } else {
            // Create a minimal valid PNG
            $imageData = $this->createMinimalPng(100, 100);
        }

        // Should not throw an exception
        $this->service->checkImagick($imageData);

        $this->assertTrue(true);
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function it_throws_exception_for_oversized_image_with_imagick(): void
    {
        config(['app.imagick.max_width' => 50]);
        config(['app.imagick.max_height' => 50]);
        config(['image.imagick.check_resource_limits' => true]);

        // Create a test image using GD
        if (extension_loaded('gd')) {
            $image = imagecreatetruecolor(100, 100);
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);
        } else {
            $imageData = $this->createMinimalPng(100, 100);
        }

        $this->expectException(ImageTranscodingException::class);

        $this->service->checkImagick($imageData);
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function it_throws_exception_for_invalid_image_data_with_imagick(): void
    {
        config(['image.imagick.check_resource_limits' => true]);

        $this->expectException(ImageTranscodingException::class);

        $this->service->checkImagick('invalid image data');
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function check_delegates_to_gd_for_gd_driver(): void
    {
        config(['image.gd.max_width' => 8000]);
        config(['image.gd.max_height' => 8000]);

        // Create a small test image
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        // Should not throw an exception
        $this->service->check($imageData, 'gd');

        $this->assertTrue(true);
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function check_delegates_to_imagick_for_imagick_driver(): void
    {
        config(['app.imagick.max_width' => 10000]);
        config(['app.imagick.max_height' => 10000]);
        config(['image.imagick.check_resource_limits' => true]);

        // Create a small test image
        if (extension_loaded('gd')) {
            $image = imagecreatetruecolor(100, 100);
            ob_start();
            imagepng($image);
            $imageData = ob_get_clean();
            imagedestroy($image);
        } else {
            $imageData = $this->createMinimalPng(100, 100);
        }

        // Should not throw an exception
        $this->service->check($imageData, 'imagick');

        $this->assertTrue(true);
    }

    #[Test]
    public function check_skips_imagick_checks_when_disabled(): void
    {
        config(['image.imagick.check_resource_limits' => false]);

        // Should not throw even with invalid data because checks are disabled
        $this->service->check('any data', 'imagick');

        $this->assertTrue(true);
    }

    #[Test]
    public function check_does_nothing_for_unknown_driver(): void
    {
        // Should not throw for unknown driver
        $this->service->check('any data', 'unknown_driver');

        $this->assertTrue(true);
    }

    /**
     * Create a minimal valid PNG image for testing
     */
    private function createMinimalPng(int $width, int $height): string
    {
        // This creates a valid PNG header with specified dimensions
        // It's a simplified version that works for dimension detection
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('N', 13).'IHDR'.pack('N', $width).pack('N', $height).
            "\x08\x02\x00\x00\x00"; // 8-bit RGB, no interlace
        $crc = pack('N', crc32(substr($ihdr, 4)));
        $iend = pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        return $signature.$ihdr.$crc.$iend;
    }
}
