<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Image;

use App\Services\Image\ImageDimensionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ImageDimensionService::class)]
class ImageDimensionServiceTest extends TestCase
{
    private ImageDimensionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ImageDimensionService::class);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_gets_dimensions_of_image(): void
    {
        $image = imagecreatetruecolor(200, 150);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $dimensions = $this->service->get($imageData);

        $this->assertEquals(200, $dimensions['width']);
        $this->assertEquals(150, $dimensions['height']);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_gets_aspect_ratio_of_landscape_image(): void
    {
        $image = imagecreatetruecolor(200, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $ratio = $this->service->getAspectRatio($imageData);

        $this->assertEquals(2.0, $ratio);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_gets_aspect_ratio_of_portrait_image(): void
    {
        $image = imagecreatetruecolor(100, 200);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $ratio = $this->service->getAspectRatio($imageData);

        $this->assertEquals(0.5, $ratio);
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_detects_landscape_orientation(): void
    {
        $image = imagecreatetruecolor(200, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($this->service->isLandscape($imageData));
        $this->assertFalse($this->service->isPortrait($imageData));
        $this->assertFalse($this->service->isSquare($imageData));
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_detects_portrait_orientation(): void
    {
        $image = imagecreatetruecolor(100, 200);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($this->service->isPortrait($imageData));
        $this->assertFalse($this->service->isLandscape($imageData));
        $this->assertFalse($this->service->isSquare($imageData));
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_detects_square_orientation(): void
    {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($this->service->isSquare($imageData));
        $this->assertFalse($this->service->isLandscape($imageData));
        $this->assertFalse($this->service->isPortrait($imageData));
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_detects_nearly_square_within_tolerance(): void
    {
        // 100x95 is within 5% tolerance of being square
        $image = imagecreatetruecolor(100, 95);
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $this->assertTrue($this->service->isSquare($imageData, 0.1)); // 10% tolerance
        $this->assertFalse($this->service->isSquare($imageData, 0.01)); // 1% tolerance
    }

    #[Test]
    public function it_calculates_scaled_dimensions_for_landscape_image(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(2000, 1000, 500);

        $this->assertEquals(500, $dimensions['width']);
        $this->assertEquals(250, $dimensions['height']);
    }

    #[Test]
    public function it_calculates_scaled_dimensions_for_portrait_image(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(1000, 2000, 500);

        $this->assertEquals(250, $dimensions['width']);
        $this->assertEquals(500, $dimensions['height']);
    }

    #[Test]
    public function it_calculates_scaled_dimensions_for_square_image(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(1000, 1000, 500);

        $this->assertEquals(500, $dimensions['width']);
        $this->assertEquals(500, $dimensions['height']);
    }

    #[Test]
    public function it_returns_original_dimensions_when_smaller_than_max(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(200, 150, 500);

        $this->assertEquals(200, $dimensions['width']);
        $this->assertEquals(150, $dimensions['height']);
    }

    #[Test]
    public function it_returns_original_dimensions_when_equal_to_max(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(500, 500, 500);

        $this->assertEquals(500, $dimensions['width']);
        $this->assertEquals(500, $dimensions['height']);
    }

    #[Test]
    public function it_maintains_aspect_ratio_when_scaling(): void
    {
        $originalWidth = 1920;
        $originalHeight = 1080;
        $originalRatio = $originalWidth / $originalHeight;

        $dimensions = $this->service->calculateScaledDimensions($originalWidth, $originalHeight, 500);

        $scaledRatio = $dimensions['width'] / $dimensions['height'];

        // Allow for small rounding differences
        $this->assertEqualsWithDelta($originalRatio, $scaledRatio, 0.01);
    }

    #[Test]
    public function it_scales_wide_panoramic_image_correctly(): void
    {
        // Very wide panoramic image (5:1 ratio)
        $dimensions = $this->service->calculateScaledDimensions(5000, 1000, 500);

        $this->assertEquals(500, $dimensions['width']);
        $this->assertEquals(100, $dimensions['height']);
    }

    #[Test]
    public function it_scales_tall_image_correctly(): void
    {
        // Very tall image (1:5 ratio)
        $dimensions = $this->service->calculateScaledDimensions(1000, 5000, 500);

        $this->assertEquals(100, $dimensions['width']);
        $this->assertEquals(500, $dimensions['height']);
    }

    #[Test]
    public function it_handles_small_images(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(10, 5, 500);

        $this->assertEquals(10, $dimensions['width']);
        $this->assertEquals(5, $dimensions['height']);
    }

    #[Test]
    public function it_handles_minimum_size_images(): void
    {
        $dimensions = $this->service->calculateScaledDimensions(1, 1, 500);

        $this->assertEquals(1, $dimensions['width']);
        $this->assertEquals(1, $dimensions['height']);
    }
}
