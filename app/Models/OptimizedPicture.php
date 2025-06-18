<?php

namespace App\Models;

use Database\Factories\OptimizedPictureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $picture_id
 * @property string $variant
 * @property string $path
 * @property string $format
 * @property mixed $use_factory
 * @property int|null $pictures_count
 * @property-read Picture $picture
 */
class OptimizedPicture extends Model
{
    /** @use HasFactory<OptimizedPictureFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'picture_id',
        'variant',
        'path',
        'format',
    ];

    const THUMBNAIL_SIZE = 256;

    const SMALL_SIZE = 512;

    const MEDIUM_SIZE = 1024;

    const LARGE_SIZE = 2048;

    const VARIANTS = [
        'thumbnail',
        'small',
        'medium',
        'large',
        'full',
    ];

    const FORMATS = [
        'avif',
        'webp',
        'jpg',
    ];

    protected static function booted(): void
    {
        static::deleting(function (OptimizedPicture $optimizedPicture) {
            $optimizedPicture->deleteFile();
        });
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    public function deleteFile(string $disk = 'public'): void
    {
        if (Storage::disk($disk)->exists($this->path)) {
            Storage::disk($disk)->delete($this->path);
        }

        if (config('app.cdn_disk')) {
            $this->deleteFile(config('app.cdn_disk'));
        }
    }
}
