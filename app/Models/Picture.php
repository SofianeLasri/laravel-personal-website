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
        // $imageTranscodingService = new ImageTranscodingService(new Driver);
        $imageTranscodingService = app(ImageTranscodingService::class);

        $dimensions = $imageTranscodingService->getDimensions($originalImage);
        $highestDimension = max($dimensions['width'], $dimensions['height']);

        $variants = [
            'thumbnail' => OptimizedPicture::THUMBNAIL_SIZE,
            'small' => OptimizedPicture::SMALL_SIZE,
            'medium' => OptimizedPicture::MEDIUM_SIZE,
            'large' => OptimizedPicture::LARGE_SIZE,
            'full' => $highestDimension,
        ];

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
        }

        $this->update([
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

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

        return $imageTranscodingService->transcode($originalImage, $dimensionToUse, $format);
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

        foreach ($optimizedImages as $variantName => $image) {
            $path = Str::beforeLast($this->path_original, '.')."_$variantName.$format";
            Storage::disk('public')->put($path, $image);

            if (config('app.cdn_disk')) {
                Storage::disk(config('app.cdn_disk'))->put($path, $image);
            }

            $this->optimizedPictures()->create([
                'variant' => $variantName,
                'path' => $path,
                'format' => $format,
            ]);
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
}
