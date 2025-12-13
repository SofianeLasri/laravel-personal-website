<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Exceptions\ImageTranscodingException;
use Exception;
use Illuminate\Support\Facades\Log;
use Imagick;

/**
 * Service for checking resource limits before image processing
 */
class ResourceLimitCheckerService
{
    /**
     * Perform pre-flight checks for a specific driver
     *
     * @throws ImageTranscodingException
     */
    public function check(string $source, string $driverName): void
    {
        if ($driverName === 'imagick' && config('image.imagick.check_resource_limits', true)) {
            $this->checkImagick($source);
        }

        if ($driverName === 'gd') {
            $this->checkGd($source);
        }
    }

    /**
     * Check Imagick resource limits
     *
     * @throws ImageTranscodingException
     */
    public function checkImagick(string $source): void
    {
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
        } catch (ImageTranscodingException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::warning('Failed to check Imagick resource limits', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check GD limits
     *
     * @throws ImageTranscodingException
     */
    public function checkGd(string $source): void
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
        } catch (ImageTranscodingException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::warning('Failed to check GD limits', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current Imagick resource limits
     *
     * @return array<string, int>
     */
    public function getImagickLimits(): array
    {
        if (! extension_loaded('imagick')) {
            return [];
        }

        return [
            'area' => Imagick::getResourceLimit(Imagick::RESOURCETYPE_AREA),
            'memory' => Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY),
            'disk' => Imagick::getResourceLimit(Imagick::RESOURCETYPE_DISK),
        ];
    }

    /**
     * Get configured limits
     *
     * @return array<string, array<string, int|null>>
     */
    public function getConfiguredLimits(): array
    {
        return [
            'imagick' => [
                'max_width' => config('app.imagick.max_width'),
                'max_height' => config('app.imagick.max_height'),
            ],
            'gd' => [
                'max_width' => config('image.gd.max_width', 8000),
                'max_height' => config('image.gd.max_height', 8000),
            ],
        ];
    }
}
