<?php

namespace App\Models;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $path
 * @property int|null $cover_picture_id
 * @property string $bunny_video_id
 * @property int|null $file_size
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property VideoStatus $status
 * @property VideoVisibility $visibility
 * @property mixed $use_factory
 * @property int|null $cover_pictures_count
 * @property-read Picture|null $coverPicture
 */
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'cover_picture_id',
        'bunny_video_id',
        'file_size',
        'status',
        'visibility',
    ];

    protected $casts = [
        'status' => VideoStatus::class,
        'visibility' => VideoVisibility::class,
    ];

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }

    /**
     * @return BelongsToMany<Creation, $this>
     */
    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    /**
     * @return HasMany<BlogContentVideo, $this>
     */
    public function blogContentVideos(): HasMany
    {
        return $this->hasMany(BlogContentVideo::class);
    }
}
