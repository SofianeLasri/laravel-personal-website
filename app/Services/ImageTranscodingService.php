<?php

namespace App\Services;

use App\Exceptions\ImageTranscodingException;
use Exception;
use Illuminate\Support\Facades\Log;
use Imagick;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\RuntimeException;
use Intervention\Image\ImageManager;
use InvalidArgumentException;

class ImageTranscodingService
{
    /**
     * @var array<string>
     */
    protected array $availableDrivers = [];

    /**
     * @var array<string, ImageManager>
     */
    protected array $driverManagers = [];

    protected NotificationService $notificationService;

    /**
     * @throws ImageTranscodingException
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->detectAvailableDrivers();
        $this->initializeDrivers();
    }

    /**
     * Detect which drivers are available on the system
     *
     * @throws ImageTranscodingException
     */
    protected function detectAvailableDrivers(): void
    {
        $configuredDrivers = config('image.drivers', ['imagick', 'gd']);

        foreach ($configuredDrivers as $driver) {
            if ($this->isDriverAvailable($driver)) {
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
    protected function isDriverAvailable(string $driver): bool
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
    protected function initializeDrivers(): void
    {
        foreach ($this->availableDrivers as $driver) {
            $this->driverManagers[$driver] = $this->createDriverManager($driver);
        }
    }

    /**
     * Create an ImageManager instance for a specific driver
     */
    protected function createDriverManager(string $driver): ImageManager
    {
        $options = config("image.{$driver}.options", []);

        return match ($driver) {
            'imagick' => new ImageManager(new ImagickDriver, $options),
            'gd' => new ImageManager(new GdDriver, $options),
            default => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Transcode an image to a new resolution with automatic fallback
     *
     * @param  string  $source  The source image path or content
     * @param  int|null  $resolution  The new resolution to transcode the image to
     * @param  string  $codec  The codec to use for transcoding
     * @return string The transcoded image content
     *
     * @throws ImageTranscodingException
     */
    public function transcode(string $source, ?int $resolution = null, string $codec = 'avif'): string
    {
        $startTime = microtime(true);
        $attempts = [];
        $lastException = null;

        // Get prioritized drivers for this format
        $driversToTry = $this->getDriversForFormat($codec);

        foreach ($driversToTry as $driverName) {
            try {
                Log::debug('Attempting image transcoding', [
                    'driver' => $driverName,
                    'codec' => $codec,
                    'resolution' => $resolution,
                ]);

                $result = $this->transcodeWithDriver($source, $resolution, $codec, $driverName);

                $processingTime = microtime(true) - $startTime;

                Log::info('Image transcoding successful', [
                    'driver' => $driverName,
                    'codec' => $codec,
                    'resolution' => $resolution,
                    'processing_time' => round($processingTime, 2),
                    'output_size' => strlen($result),
                    'fallback_used' => count($attempts) > 0,
                ]);

                // Send notification if fallback was used
                if (count($attempts) > 0 && config('image.fallback.notify_on_fallback', true)) {
                    $this->notifyFallbackUsed($driverName, $attempts, $codec);
                }

                return $result;

            } catch (ImageTranscodingException $e) {
                $attempts[$driverName] = $e->getMessage();
                $lastException = $e;

                Log::warning('Driver failed, trying next', [
                    'failed_driver' => $driverName,
                    'error' => $e->getMessage(),
                    'remaining_drivers' => array_slice($driversToTry, (int) array_search($driverName, $driversToTry) + 1),
                ]);

                // If this error shouldn't trigger a fallback, break early
                if (! $e->shouldTriggerFallback()) {
                    break;
                }
            }
        }

        // All drivers failed
        $totalTime = microtime(true) - $startTime;

        Log::error('All image transcoding drivers failed', [
            'codec' => $codec,
            'resolution' => $resolution,
            'attempts' => $attempts,
            'total_time' => round($totalTime, 2),
        ]);

        throw ImageTranscodingException::allDriversFailed($attempts, [
            'codec' => $codec,
            'resolution' => $resolution,
            'total_time' => $totalTime,
        ]);
    }

    /**
     * Transcode with a specific driver
     *
     * @throws ImageTranscodingException
     */
    protected function transcodeWithDriver(string $source, ?int $resolution, string $codec, string $driverName): string
    {
        $imageManager = $this->driverManagers[$driverName];

        try {
            // Check format support
            if (! $this->driverSupportsFormat($driverName, $codec)) {
                throw ImageTranscodingException::unsupportedFormat($codec, $driverName);
            }

            // Pre-flight checks for the driver
            $this->performPreflightChecks($source, $driverName);

            // Read the image
            $image = $imageManager->read($source);

            // Apply resolution if specified
            if ($resolution) {
                $image->scale($resolution);
            }

            // Encode with the specified codec
            $encodedImage = match ($codec) {
                'jpeg' => $image->encode(new JpegEncoder(config('image.quality.jpeg', 85)))->toString(),
                'webp' => $image->encode(new WebpEncoder(config('image.quality.webp', 80)))->toString(),
                'png' => $image->encode(new PngEncoder)->toString(),
                'avif' => $image->encode(new AvifEncoder(config('image.quality.avif', 75)))->toString(),
                default => throw ImageTranscodingException::unsupportedFormat($codec, $driverName),
            };

            // Validate the output
            if (empty($encodedImage)) {
                throw ImageTranscodingException::emptyOutput($driverName, [
                    'codec' => $codec,
                    'resolution' => $resolution,
                ]);
            }

            return $encodedImage;

        } catch (RuntimeException $e) {
            throw ImageTranscodingException::imagickFailed($e->getMessage(), [
                'codec' => $codec,
                'resolution' => $resolution,
            ], $e);
        } catch (Exception $e) {
            // Handle GD-specific errors or other exceptions
            if ($driverName === 'gd') {
                throw ImageTranscodingException::gdFailed($e->getMessage(), [
                    'codec' => $codec,
                    'resolution' => $resolution,
                ], $e);
            } else {
                throw ImageTranscodingException::imagickFailed($e->getMessage(), [
                    'codec' => $codec,
                    'resolution' => $resolution,
                ], $e);
            }
        }
    }

    /**
     * Perform pre-flight checks for a specific driver
     */
    protected function performPreflightChecks(string $source, string $driverName): void
    {
        if ($driverName === 'imagick' && config('image.imagick.check_resource_limits', true)) {
            $this->checkImagickResourceLimits($source);
        }

        if ($driverName === 'gd') {
            $this->checkGdLimits($source);
        }
    }

    /**
     * Check Imagick resource limits
     */
    protected function checkImagickResourceLimits(string $source): void
    {
        // Get image dimensions to check against limits
        try {
            $imageInfo = getimagesizefromstring($source);
            if ($imageInfo === false) {
                throw ImageTranscodingException::imagickFailed('Unable to determine image dimensions');
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $area = $width * $height;

            $maxWidth = config('app.imagick.max_width');
            $maxHeight = config('app.imagick.max_height');
            $maxArea = Imagick::getResourceLimit(Imagick::RESOURCETYPE_AREA);

            if ($width > $maxWidth || $height > $maxHeight || $area > $maxArea) {
                throw ImageTranscodingException::resourceLimitExceeded('imagick', 'dimensions', [
                    'image_width' => $width,
                    'image_height' => $height,
                    'image_area' => $area,
                    'max_width' => $maxWidth,
                    'max_height' => $maxHeight,
                    'max_area' => $maxArea,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to check Imagick resource limits', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check GD limits
     */
    protected function checkGdLimits(string $source): void
    {
        try {
            $imageInfo = getimagesizefromstring($source);
            if ($imageInfo === false) {
                throw ImageTranscodingException::gdFailed('Unable to determine image dimensions');
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];

            $maxWidth = config('image.gd.max_width', 8000);
            $maxHeight = config('image.gd.max_height', 8000);

            if ($width > $maxWidth || $height > $maxHeight) {
                throw ImageTranscodingException::resourceLimitExceeded('gd', 'dimensions', [
                    'image_width' => $width,
                    'image_height' => $height,
                    'max_width' => $maxWidth,
                    'max_height' => $maxHeight,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to check GD limits', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get prioritized drivers for a specific format
     *
     * @return array<string>
     */
    protected function getDriversForFormat(string $format): array
    {
        $driversToTry = [];

        foreach ($this->availableDrivers as $driver) {
            if ($this->driverSupportsFormat($driver, $format)) {
                $driversToTry[] = $driver;
            }
        }

        // If no driver supports the format, try format fallback
        if (empty($driversToTry)) {
            $fallbackFormat = config("image.format_fallbacks.{$format}");
            if ($fallbackFormat) {
                Log::info("Format {$format} not supported, trying fallback format {$fallbackFormat}");

                return $this->getDriversForFormat($fallbackFormat);
            }
        }

        return $driversToTry;
    }

    /**
     * Check if a driver supports a specific format
     */
    protected function driverSupportsFormat(string $driver, string $format): bool
    {
        $supportedFormats = config("image.format_support.{$driver}", []);

        return in_array($format, $supportedFormats);
    }

    /**
     * Send notification when fallback is used
     *
     * @param  array<string, string>  $failedAttempts
     */
    protected function notifyFallbackUsed(string $successfulDriver, array $failedAttempts, string $codec): void
    {
        $this->notificationService->warning(
            'Fallback driver utilisé pour l\'optimisation d\'image',
            "Le driver principal a échoué et le fallback '{$successfulDriver}' a été utilisé avec succès pour le format {$codec}.",
            [
                'successful_driver' => $successfulDriver,
                'failed_attempts' => $failedAttempts,
                'codec' => $codec,
            ]
        );
    }

    /**
     * Get the dimensions of an image
     *
     * @param  string  $source  The source image path or content
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $source): array
    {
        // Try to get dimensions using basic PHP functions first (faster)
        $imageInfo = getimagesizefromstring($source);
        if ($imageInfo !== false) {
            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
            ];
        }

        // Fallback to using the first available driver
        $primaryDriver = $this->availableDrivers[0];
        $imageManager = $this->driverManagers[$primaryDriver];
        $image = $imageManager->read($source);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Get available drivers
     *
     * @return array<string>
     */
    public function getAvailableDrivers(): array
    {
        return $this->availableDrivers;
    }

    /**
     * Check if fallback is enabled and available
     */
    public function isFallbackAvailable(): bool
    {
        return config('image.fallback.enabled', true) && count($this->availableDrivers) > 1;
    }
}
