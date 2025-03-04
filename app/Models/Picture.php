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

    public function optimizedPictures(): Picture|HasMany
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
        $imageTranscodingService = new ImageTranscodingService(new Driver);

        $dimensions = $imageTranscodingService->getDimensions($originalImage);
        $highestDimension = max($dimensions['width'], $dimensions['height']);

        $thumbnailOptimizedDimension = $this->getOptimizedDimension(OptimizedPicture::THUMBNAIL_SIZE, $highestDimension);
        $smallOptimizedDimension = $this->getOptimizedDimension(OptimizedPicture::SMALL_SIZE, $highestDimension);
        $mediumOptimizedDimension = $this->getOptimizedDimension(OptimizedPicture::MEDIUM_SIZE, $highestDimension);
        $largeOptimizedDimension = $this->getOptimizedDimension(OptimizedPicture::LARGE_SIZE, $highestDimension);

        foreach (OptimizedPicture::FORMATS as $format) {
            $fullsizeImage = $imageTranscodingService->transcode($originalImage, $highestDimension, $format);
            $thumbnailImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $fullsizeImage, $thumbnailOptimizedDimension, $highestDimension, $format);
            $smallImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $thumbnailImage, $smallOptimizedDimension, $highestDimension, $format);
            $mediumImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $smallImage, $mediumOptimizedDimension, $highestDimension, $format);
            $largeImage = $this->transcodeIfItIsWorthIt($imageTranscodingService, $mediumImage, $largeOptimizedDimension, $highestDimension, $format);

            if (! $thumbnailImage || ! $smallImage || ! $mediumImage || ! $largeImage) {
                Log::error('UploadedPicture optimization failed: transcoding failed', [
                    'path' => $this->path_original,
                ]);

                return;
            }

            $thumbnailPath = Str::beforeLast($this->path_original, '.').'_thumbnail.'.$format;
            $smallPath = Str::beforeLast($this->path_original, '.').'_small.'.$format;
            $mediumPath = Str::beforeLast($this->path_original, '.').'_medium.'.$format;
            $largePath = Str::beforeLast($this->path_original, '.').'_large.'.$format;
            $fullsizeImage = Str::beforeLast($this->path_original, '.').'_fullsize.'.$format;

            Storage::disk('public')->put($thumbnailPath, $thumbnailImage);
            Storage::disk('public')->put($smallPath, $smallImage);
            Storage::disk('public')->put($mediumPath, $mediumImage);
            Storage::disk('public')->put($largePath, $largeImage);
            Storage::disk('public')->put($fullsizeImage, $fullsizeImage);

            if (config('app.cdn_disk')) {
                Storage::disk(config('app.cdn_disk'))->put($thumbnailPath, $thumbnailImage);
                Storage::disk(config('app.cdn_disk'))->put($smallPath, $smallImage);
                Storage::disk(config('app.cdn_disk'))->put($mediumPath, $mediumImage);
                Storage::disk(config('app.cdn_disk'))->put($largePath, $largeImage);
                Storage::disk(config('app.cdn_disk'))->put($fullsizeImage, $fullsizeImage);
            }

            $this->optimizedPictures()->create([
                'variant' => 'thumbnail',
                'path' => $thumbnailPath,
                'format' => $format,
            ]);

            $this->optimizedPictures()->create([
                'variant' => 'small',
                'path' => $smallPath,
                'format' => $format,
            ]);

            $this->optimizedPictures()->create([
                'variant' => 'medium',
                'path' => $mediumPath,
                'format' => $format,
            ]);

            $this->optimizedPictures()->create([
                'variant' => 'large',
                'path' => $largePath,
                'format' => $format,
            ]);

            $this->optimizedPictures()->create([
                'variant' => 'full',
                'path' => $fullsizeImage,
                'format' => $format,
            ]);
        }

        $this->update([
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);

        $this->deleteOriginal();
    }

    private function transcodeIfItIsWorthIt($imageTranscodingService, $previousImage, $optimizedDimension, $highestDimension, $format): string
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
