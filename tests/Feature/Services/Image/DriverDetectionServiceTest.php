<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Image;

use App\Services\Image\DriverDetectionService;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DriverDetectionService::class)]
class DriverDetectionServiceTest extends TestCase
{
    #[Test]
    public function it_detects_available_drivers(): void
    {
        $service = app(DriverDetectionService::class);

        $availableDrivers = $service->getAvailable();

        $this->assertIsArray($availableDrivers);
        $this->assertNotEmpty($availableDrivers);

        // At least one driver should be available (gd or imagick)
        $this->assertTrue(
            in_array('gd', $availableDrivers) || in_array('imagick', $availableDrivers)
        );
    }

    #[Test]
    public function it_checks_gd_availability(): void
    {
        $service = app(DriverDetectionService::class);

        $isAvailable = $service->isAvailable('gd');

        // Should match actual extension status
        $this->assertEquals(extension_loaded('gd'), $isAvailable);
    }

    #[Test]
    public function it_checks_imagick_availability(): void
    {
        $service = app(DriverDetectionService::class);

        $isAvailable = $service->isAvailable('imagick');

        // Should match actual extension status
        $this->assertEquals(
            extension_loaded('imagick') && class_exists(\Imagick::class),
            $isAvailable
        );
    }

    #[Test]
    public function it_returns_false_for_unknown_driver(): void
    {
        $service = app(DriverDetectionService::class);

        $isAvailable = $service->isAvailable('unknown_driver');

        $this->assertFalse($isAvailable);
    }

    #[Test]
    public function it_returns_primary_driver(): void
    {
        $service = app(DriverDetectionService::class);

        $primaryDriver = $service->getPrimaryDriver();

        $this->assertIsString($primaryDriver);
        $this->assertContains($primaryDriver, $service->getAvailable());
    }

    #[Test]
    public function it_returns_primary_manager(): void
    {
        $service = app(DriverDetectionService::class);

        $manager = $service->getPrimaryManager();

        $this->assertInstanceOf(ImageManager::class, $manager);
    }

    #[Test]
    public function it_creates_manager_for_available_driver(): void
    {
        $service = app(DriverDetectionService::class);
        $primaryDriver = $service->getPrimaryDriver();

        $manager = $service->createManager($primaryDriver);

        $this->assertInstanceOf(ImageManager::class, $manager);
    }

    #[Test]
    public function it_throws_exception_for_unsupported_driver_manager(): void
    {
        $service = app(DriverDetectionService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver: invalid');

        $service->createManager('invalid');
    }

    #[Test]
    public function it_throws_exception_when_getting_unavailable_driver_manager(): void
    {
        $service = app(DriverDetectionService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver not available: nonexistent');

        $service->getManager('nonexistent');
    }

    #[Test]
    public function it_gets_manager_for_available_driver(): void
    {
        $service = app(DriverDetectionService::class);
        $primaryDriver = $service->getPrimaryDriver();

        $manager = $service->getManager($primaryDriver);

        $this->assertInstanceOf(ImageManager::class, $manager);
    }

    #[Test]
    public function it_checks_format_support(): void
    {
        config(['image.format_support.gd' => ['jpg', 'png', 'gif', 'webp']]);
        config(['image.format_support.imagick' => ['jpg', 'png', 'gif', 'webp', 'avif']]);

        $service = app(DriverDetectionService::class);

        // GD should support common formats
        if ($service->isAvailable('gd')) {
            $this->assertTrue($service->supportsFormat('gd', 'jpg'));
            $this->assertTrue($service->supportsFormat('gd', 'png'));
        }

        // Imagick should support AVIF
        if ($service->isAvailable('imagick')) {
            $this->assertTrue($service->supportsFormat('imagick', 'avif'));
        }
    }

    #[Test]
    public function it_returns_false_for_unsupported_format(): void
    {
        config(['image.format_support.gd' => ['jpg', 'png']]);

        $service = app(DriverDetectionService::class);

        $this->assertFalse($service->supportsFormat('gd', 'nonexistent_format'));
    }

    #[Test]
    public function it_gets_drivers_for_format(): void
    {
        config(['image.format_support.gd' => ['jpg', 'png', 'webp']]);
        config(['image.format_support.imagick' => ['jpg', 'png', 'webp', 'avif']]);

        $service = app(DriverDetectionService::class);

        $driversForJpg = $service->getForFormat('jpg');

        $this->assertIsArray($driversForJpg);
        // At least one driver should support jpg
        $this->assertNotEmpty($driversForJpg);
    }

    #[Test]
    public function it_returns_empty_array_for_unsupported_format_without_fallback(): void
    {
        config(['image.format_support.gd' => ['jpg']]);
        config(['image.format_support.imagick' => ['jpg']]);
        config(['image.format_fallbacks' => []]);

        $service = app(DriverDetectionService::class);

        $drivers = $service->getForFormat('totally_unsupported_format');

        $this->assertIsArray($drivers);
        $this->assertEmpty($drivers);
    }

    #[Test]
    public function it_checks_fallback_availability(): void
    {
        config(['image.fallback.enabled' => true]);

        $service = app(DriverDetectionService::class);

        $hasFallback = $service->isFallbackAvailable();

        // Fallback is available only if enabled AND more than one driver is available
        $expectedFallback = config('image.fallback.enabled', true) && count($service->getAvailable()) > 1;
        $this->assertEquals($expectedFallback, $hasFallback);
    }

    #[Test]
    public function it_returns_false_when_fallback_disabled(): void
    {
        config(['image.fallback.enabled' => false]);

        $service = app(DriverDetectionService::class);

        $this->assertFalse($service->isFallbackAvailable());
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function it_creates_gd_manager_when_available(): void
    {
        $service = app(DriverDetectionService::class);

        $manager = $service->createManager('gd');

        $this->assertInstanceOf(ImageManager::class, $manager);
    }

    #[Test]
    #[RequiresPhpExtension('imagick')]
    public function it_creates_imagick_manager_when_available(): void
    {
        $service = app(DriverDetectionService::class);

        $manager = $service->createManager('imagick');

        $this->assertInstanceOf(ImageManager::class, $manager);
    }
}
