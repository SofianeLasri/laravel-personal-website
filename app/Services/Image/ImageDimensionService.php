<?php

declare(strict_types=1);

namespace App\Services\Image;

/**
 * Service for image dimension utilities
 */
class ImageDimensionService
{
    public function __construct(
        private readonly DriverDetectionService $driverDetection
    ) {}

    /**
     * Get the dimensions of an image
     *
     * @param  string  $source  The source image path or content
     * @return array{width: int, height: int}
     */
    public function get(string $source): array
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
        $imageManager = $this->driverDetection->getPrimaryManager();
        $image = $imageManager->read($source);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Get the aspect ratio of an image
     *
     * @param  string  $source  The source image path or content
     */
    public function getAspectRatio(string $source): float
    {
        $dimensions = $this->get($source);

        return $dimensions['width'] / $dimensions['height'];
    }

    /**
     * Check if an image is landscape oriented
     */
    public function isLandscape(string $source): bool
    {
        return $this->getAspectRatio($source) > 1;
    }

    /**
     * Check if an image is portrait oriented
     */
    public function isPortrait(string $source): bool
    {
        return $this->getAspectRatio($source) < 1;
    }

    /**
     * Check if an image is square
     */
    public function isSquare(string $source, float $tolerance = 0.05): bool
    {
        $ratio = $this->getAspectRatio($source);

        return abs($ratio - 1) <= $tolerance;
    }

    /**
     * Calculate new dimensions maintaining aspect ratio
     *
     * @param  int  $originalWidth  Original width
     * @param  int  $originalHeight  Original height
     * @param  int  $maxSize  Maximum size for the largest dimension
     * @return array{width: int, height: int}
     */
    public function calculateScaledDimensions(int $originalWidth, int $originalHeight, int $maxSize): array
    {
        if ($originalWidth <= $maxSize && $originalHeight <= $maxSize) {
            return [
                'width' => $originalWidth,
                'height' => $originalHeight,
            ];
        }

        $ratio = $originalWidth / $originalHeight;

        if ($originalWidth > $originalHeight) {
            return [
                'width' => $maxSize,
                'height' => (int) round($maxSize / $ratio),
            ];
        }

        return [
            'width' => (int) round($maxSize * $ratio),
            'height' => $maxSize,
        ];
    }
}
