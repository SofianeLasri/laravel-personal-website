<?php

namespace App\Models;

use App\Services\ImageTranscodingService;
use Database\Factories\PictureFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $filename
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size
 * @property string|null $path_original
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $optimized_pictures_count
 * @property-read Collection|OptimizedPicture[] $optimizedPictures
 */
class Picture extends Model
{
    /** @use HasFactory<PictureFactory> */
    use HasFactory;

    protected $fillable = [
        'filename',
        'width',
        'height',
        'size',
        'path_original',
    ];

    /**
     * @return HasMany<OptimizedPicture, $this>
     */
    public function optimizedPictures(): HasMany
    {
        return $this->hasMany(OptimizedPicture::class);
    }

    /**
     * Get the optimized pictures for the picture.
     *
     * @param  string  $variant  thumbnail|small|medium|large|full
     * @param  string  $format  avif|webp|jpg
     */
    public function getOptimizedPicture(string $variant, string $format): ?OptimizedPicture
    {
        return $this->optimizedPictures->first(function (OptimizedPicture $optimizedPicture) use ($variant, $format) {
            return $optimizedPicture->variant === $variant && $optimizedPicture->format === $format;
        });
    }

    public function optimize(): void
    {
        if (! $this->hasValidOriginalPath()) {
            Log::warning('UploadedPicture optimization failed: path_original is empty');

            return;
        }

        if (! Storage::disk('public')->exists($this->path_original)) {
            Log::warning('UploadedPicture optimization failed: file does not exist', [
                'path' => $this->path_original,
            ]);

            return;
        }

        $originalImage = Storage::disk('public')->get($this->path_original);
        $imageTranscodingService = app(ImageTranscodingService::class);
        $imageCacheService = app(\App\Services\ImageCacheService::class);

        // Try to use cache if enabled
        if (config('images.cache.enabled', false)) {
            $checksum = $imageCacheService->calculateChecksum($originalImage);
            $cachedOptimizations = $imageCacheService->getCachedOptimizations($checksum);

            if ($cachedOptimizations) {
                if ($imageCacheService->copyCachedFiles($cachedOptimizations, $this)) {
                    Log::info('Used cached optimizations for picture', [
                        'picture_id' => $this->id,
                        'checksum' => $checksum,
                    ]);

                    return;
                }
            }
        }

        // Fallback to normal optimization
        $this->optimizeWithoutCache($imageTranscodingService, $originalImage, $imageCacheService);
    }

    /**
     * Perform optimization without using cache (or when cache miss occurs)
     */
    private function optimizeWithoutCache(ImageTranscodingService $imageTranscodingService, string $originalImage, ?\App\Services\ImageCacheService $imageCacheService = null): void
    {
        $dimensions = $imageTranscodingService->getDimensions($originalImage);
        $highestDimension = max($dimensions['width'], $dimensions['height']);

        $variants = [
            'thumbnail' => OptimizedPicture::THUMBNAIL_SIZE,
            'small' => OptimizedPicture::SMALL_SIZE,
            'medium' => OptimizedPicture::MEDIUM_SIZE,
            'large' => OptimizedPicture::LARGE_SIZE,
            'full' => $highestDimension,
        ];

        $allOptimizedImages = [];

        foreach (OptimizedPicture::FORMATS as $format) {
            $optimizedImages = [];

            foreach ($variants as $variant => $size) {
                $optimizedDimension = $this->getOptimizedDimension($size, $highestDimension);
                $optimizedImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $optimizedDimension, $highestDimension, $format);

                if ($optimizedImage === null) {
                    Log::error('UploadedPicture optimization failed: transcoding failed', [
                        'path' => $this->path_original,
                    ]);

                    return;
                }

                $optimizedImages[$variant] = $optimizedImage;
            }

            $this->storeOptimizedImages($optimizedImages, $format);
            $allOptimizedImages[$format] = $optimizedImages;
        }

        $this->update([
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

        // Store in cache if enabled and cache service is available
        if ($imageCacheService && config('images.cache.enabled', false)) {
            $checksum = $imageCacheService->calculateChecksum($originalImage);
            $imageCacheService->storeCachedOptimizations(
                $checksum,
                $allOptimizedImages,
                $dimensions['width'],
                $dimensions['height']
            );
        }

        // $this->deleteOriginal();
    }

    /**
     * Transcode the image if it is worth it.
     *
     * This method checks if the optimized dimension is less than the highest dimension.
     */
    private function transcodeIfItIsWorthIt(ImageTranscodingService $imageTranscodingService, int $optimizedDimension, int $highestDimension, string $format): ?string
    {
        if (! $this->hasValidOriginalPath()) {
            Log::warning('UploadedPicture transcoding failed: path_original is empty');

            return null;
        }

        $originalImage = Storage::disk('public')->get($this->path_original);

        if ($originalImage === null || $originalImage === '') {
            Log::warning('UploadedPicture transcoding failed: original image is empty', [
                'path' => $this->path_original,
            ]);

            return null;
        }

        $dimensionToUse = $optimizedDimension >= $highestDimension ? null : $optimizedDimension;

        try {
            return $imageTranscodingService->transcode($originalImage, $dimensionToUse, $format);
        } catch (\App\Exceptions\ImageTranscodingException $e) {
            Log::error('Image transcoding failed with specific error', [
                'picture_id' => $this->id,
                'error_code' => $e->getErrorCode()->value,
                'driver_used' => $e->getDriverUsed(),
                'fallback_attempted' => $e->getFallbackAttempted(),
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            // Send notification for critical errors only
            if ($e->getSeverity() === 'critical' || $e->getSeverity() === 'error') {
                $notificationService = app(\App\Services\NotificationService::class);
                $notificationService->error(
                    'Échec critique d\'optimisation d\'image',
                    'L\'image "' . $this->filename . '" n\'a pas pu être optimisée: ' . $e->getMessage(),
                    [
                        'picture_id' => $this->id,
                        'error_details' => $e->toArray(),
                    ]
                );
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error during image transcoding', [
                'picture_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function getOptimizedDimension(int $dimension, int $highestDimension): int
    {
        return min($dimension, $highestDimension);
    }

    /**
     * Store the optimized images in the public disk and CDN disk if configured.
     *
     * **Note:** This method assumes that the optimized images are already in the correct format.
     * It does not perform any conversion.
     *
     * @param  array<string, string>  $optimizedImages
     */
    private function storeOptimizedImages(array $optimizedImages, string $format): void
    {
        if (! $this->hasValidOriginalPath()) {
            Log::warning('UploadedPicture storeOptimizedImages failed: path_original is empty');

            return;
        }

        $failedVariants = [];

        foreach ($optimizedImages as $variantName => $image) {
            // Validate image content
            if (empty($image) || strlen($image) === 0) {
                Log::error('Optimized image is empty, skipping storage', [
                    'picture_id' => $this->id,
                    'variant' => $variantName,
                    'format' => $format,
                    'filename' => $this->filename,
                ]);
                $failedVariants[] = "$variantName.$format";
                continue;
            }

            $path = Str::beforeLast($this->path_original, '.')."_$variantName.$format";
            Storage::disk('public')->put($path, $image);

            // Verify the file was written correctly
            if (!Storage::disk('public')->exists($path) || Storage::disk('public')->size($path) === 0) {
                Log::error('Failed to store optimized image or file has 0 bytes', [
                    'picture_id' => $this->id,
                    'variant' => $variantName,
                    'format' => $format,
                    'path' => $path,
                ]);
                // Delete the empty file if it exists
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                $failedVariants[] = "$variantName.$format";
                continue;
            }

            if (config('app.cdn_disk')) {
                Storage::disk(config('app.cdn_disk'))->put($path, $image);
            }

            $this->optimizedPictures()->create([
                'variant' => $variantName,
                'path' => $path,
                'format' => $format,
            ]);
        }

        // Send notification if there were failures
        if (!empty($failedVariants)) {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->error(
                'Échec d\'optimisation d\'image',
                'Certaines variantes n\'ont pas pu être créées pour l\'image "' . $this->filename . '": ' . implode(', ', $failedVariants),
                [
                    'picture_id' => $this->id,
                    'failed_variants' => $failedVariants,
                    'filename' => $this->filename,
                ]
            );
        }
    }

    public function deleteOptimized(): void
    {
        $this->optimizedPictures->each->delete();
    }

    public function deleteOriginal(): void
    {
        if ($this->path_original && Storage::disk('public')->exists($this->path_original)) {
            Storage::disk('public')->delete($this->path_original);
        }
    }

    public function getUrl(string $variant, string $format): string
    {
        $optimizedPicture = $this->getOptimizedPicture($variant, $format);

        if ($optimizedPicture) {
            if (config('app.cdn_disk')) {
                return Storage::disk(config('app.cdn_disk'))->url($optimizedPicture->path);
            }

            return Storage::disk('public')->url($optimizedPicture->path);
        }

        return '';
    }

    public function hasValidOriginalPath(): bool
    {
        return ! is_null($this->path_original) && ! empty($this->path_original);
    }

    /**
     * Force reoptimization of the picture by deleting existing optimized versions
     * and dispatching a new optimization job
     */
    public function reoptimize(): void
    {
        // Delete existing optimized pictures
        $this->deleteOptimized();

        // Dispatch new optimization job
        \App\Jobs\PictureJob::dispatch($this);

        Log::info('Picture reoptimization initiated', [
            'picture_id' => $this->id,
            'filename' => $this->filename,
        ]);
    }

    /**
     * Check if any optimized picture has invalid size (0 bytes)
     */
    public function hasInvalidOptimizedPictures(): bool
    {
        foreach ($this->optimizedPictures as $optimized) {
            if (Storage::disk('public')->exists($optimized->path)) {
                if (Storage::disk('public')->size($optimized->path) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
