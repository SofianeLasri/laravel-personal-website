<?php

namespace App\Models;

use App\Services\ImageTranscodingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;

class Picture extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'width',
        'height',
        'size',
        'path_original',
    ];

    public function optimizedPictures(): HasMany
    {
        return $this->hasMany(OptimizedPicture::class);
    }

    /**
     * Get the optimized pictures for the picture.
     *
     * @param  string  $variant  thumbnail|small|medium|large|full
     * @param  string  $format  avif|webp
     */
    public function getOptimizedPicture(string $variant, string $format): ?OptimizedPicture
    {
        return $this->optimizedPictures->first(function (OptimizedPicture $optimizedPicture) use ($variant, $format) {
            return $optimizedPicture->variant === $variant && $optimizedPicture->format === $format;
        });
    }

    public function optimize(): void
    {
        if (! Storage::disk('public')->exists($this->path_original)) {
            Log::warning('UploadedPicture optimization failed: file does not exist', [
                'path' => $this->path_original,
            ]);

            return;
        }

        $this->deleteOptimized();

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
            $previousImage = $originalImage;
            $optimizedImages = [];

            foreach ($variants as $variant => $size) {
                $optimizedDimension = $this->getOptimizedDimension($size, $highestDimension);
                $optimizedImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $previousImage, $optimizedDimension, $highestDimension, $format);

                if (empty($optimizedImage)) {
                    Log::error('UploadedPicture optimization failed: transcoding failed', [
                        'path' => $this->path_original,
                    ]);

                    return;
                }

                $optimizedImages[$variant] = $optimizedImage;
                $previousImage = $optimizedImage;
            }

            $this->storeOptimizedImages($optimizedImages, $format);
        }

        $this->update([
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

        // $this->deleteOriginal();
    }

    private function transcodeIfItIsWorthIt($imageTranscodingService, $previousImage, $optimizedDimension, $highestDimension, $format): ?string
    {
        if ($optimizedDimension < $highestDimension) {
            return $imageTranscodingService->transcode($previousImage, $optimizedDimension, $format);
        }

        return $previousImage;
    }

    private function getOptimizedDimension(int $dimension, int $highestDimension): int
    {
        return min($dimension, $highestDimension);
    }

    private function storeOptimizedImages(array $optimizedImages, string $format): void
    {
        foreach ($optimizedImages as $variant => $image) {
            $path = Str::beforeLast($this->path_original, '.')."_$variant.$format";
            Storage::disk('public')->put($path, $image);

            if (config('app.cdn_disk')) {
                Storage::disk(config('app.cdn_disk'))->put($path, $image);
            }

            $this->optimizedPictures()->create([
                'variant' => $variant,
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
}
