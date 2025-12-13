<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Exceptions\ImageTranscodingException;
use Illuminate\Support\Facades\Log;
use Imagick;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

/**
 * Service for detecting and managing image processing drivers
 */
class DriverDetectionService
{
    /**
     * @var array<string>
     */
    protected array $availableDrivers = [];

    /**
     * @var array<string, ImageManager>
     */
    protected array $driverManagers = [];

    /**
     * @throws ImageTranscodingException
     */
    public function __construct()
    {
        $this->detect();
        $this->initialize();
    }

    /**
     * Detect which drivers are available on the system
     *
     * @throws ImageTranscodingException
     */
    public function detect(): void
    {
        $this->availableDrivers = [];
        $configuredDrivers = config('image.drivers', ['imagick', 'gd']);

        foreach ($configuredDrivers as $driver) {
            if ($this->isAvailable($driver)) {
                $this->availableDrivers[] = $driver;
            }
        }

        if (empty($this->availableDrivers)) {
            throw ImageTranscodingException::allDriversFailed([
                'error' => 'No image processing drivers available',
            ]);
        }

        Log::info('Available image drivers detected', [
            'drivers' => $this->availableDrivers,
        ]);
    }

    /**
     * Check if a specific driver is available
     */
    public function isAvailable(string $driver): bool
    {
        return match ($driver) {
            'imagick' => extension_loaded('imagick') && class_exists(Imagick::class),
            'gd' => extension_loaded('gd') && function_exists('gd_info'),
            default => false,
        };
    }

    /**
     * Initialize ImageManager instances for each available driver
     */
    public function initialize(): void
    {
        foreach ($this->availableDrivers as $driver) {
            $this->driverManagers[$driver] = $this->createManager($driver);
        }
    }

    /**
     * Create an ImageManager instance for a specific driver
     */
    public function createManager(string $driver): ImageManager
    {
        $options = config("image.{$driver}.options", []);

        return match ($driver) {
            'imagick' => new ImageManager(new ImagickDriver, $options),
            'gd' => new ImageManager(new GdDriver, $options),
            default => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Get prioritized drivers for a specific format
     *
     * @return array<string>
     */
    public function getForFormat(string $format): array
    {
        $driversToTry = [];

        foreach ($this->availableDrivers as $driver) {
            if ($this->supportsFormat($driver, $format)) {
                $driversToTry[] = $driver;
            }
        }

        // If no driver supports the format, try format fallback
        if (empty($driversToTry)) {
            $fallbackFormat = config("image.format_fallbacks.{$format}");
            if ($fallbackFormat) {
                Log::info("Format {$format} not supported, trying fallback format {$fallbackFormat}");

                return $this->getForFormat($fallbackFormat);
            }
        }

        return $driversToTry;
    }

    /**
     * Check if a driver supports a specific format
     */
    public function supportsFormat(string $driver, string $format): bool
    {
        $supportedFormats = config("image.format_support.{$driver}", []);

        return in_array($format, $supportedFormats);
    }

    /**
     * Get available drivers
     *
     * @return array<string>
     */
    public function getAvailable(): array
    {
        return $this->availableDrivers;
    }

    /**
     * Get the ImageManager for a specific driver
     */
    public function getManager(string $driver): ImageManager
    {
        if (! isset($this->driverManagers[$driver])) {
            throw new InvalidArgumentException("Driver not available: {$driver}");
        }

        return $this->driverManagers[$driver];
    }

    /**
     * Check if fallback is enabled and available
     */
    public function isFallbackAvailable(): bool
    {
        return config('image.fallback.enabled', true) && count($this->availableDrivers) > 1;
    }

    /**
     * Get the primary (first available) driver
     */
    public function getPrimaryDriver(): string
    {
        return $this->availableDrivers[0];
    }

    /**
     * Get the primary ImageManager
     */
    public function getPrimaryManager(): ImageManager
    {
        return $this->driverManagers[$this->getPrimaryDriver()];
    }
}
