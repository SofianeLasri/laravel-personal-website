<?php

namespace App\Models;

use Database\Factories\BlogPostDraftFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $blog_post_id
 * @property string $slug
 * @property string $type
 * @property string $status
 * @property int $category_id
 * @property int|null $cover_picture_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $blog_posts_count
 * @property int|null $categories_count
 * @property int|null $cover_pictures_count
 * @property int|null $contents_count
 * @property-read BlogPost|null $blogPost
 * @property-read BlogCategory $category
 * @property-read Picture|null $coverPicture
 * @property-read Collection|BlogPostDraftContent[] $contents
 * @property-read GameReviewDraft|null $gameReviewDraft
 */
class BlogPostDraft extends Model
{
    /** @use HasFactory<BlogPostDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_id',
        'slug',
        'type',
        'status',
        'category_id',
        'cover_picture_id',
    ];

    protected $casts = [
        'slug' => 'string',
        'type' => 'string',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }

    /**
     * @return HasMany<BlogPostDraftContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(BlogPostDraftContent::class)->orderBy('order');
    }

    /**
     * @return HasOne<GameReviewDraft, $this>
     */
    public function gameReviewDraft(): HasOne
    {
        return $this->hasOne(GameReviewDraft::class);
    }
}
