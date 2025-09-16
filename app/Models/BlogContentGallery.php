<?php

namespace App\Models;

use Database\Factories\BlogContentGalleryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $pictures_count
 * @property-read Collection|Picture[] $pictures
 */
class BlogContentGallery extends Model
{
    /** @use HasFactory<BlogContentGalleryFactory> */
    use HasFactory;

    protected $fillable = [
        // No specific fillable fields needed
    ];

    /**
     * @return BelongsToMany<Picture, $this>
     */
    public function pictures(): BelongsToMany
    {
        return $this->belongsToMany(Picture::class, 'blog_content_gallery_pictures', 'gallery_id', 'picture_id')
            ->withPivot('order', 'caption_translation_key_id')
            ->orderBy('blog_content_gallery_pictures.order');
    }
}
