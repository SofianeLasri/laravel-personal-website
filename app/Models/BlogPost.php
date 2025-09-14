<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $type
 * @property string $status
 * @property int $category_id
 * @property int|null $cover_picture_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $categories_count
 * @property int|null $cover_pictures_count
 * @property int|null $contents_count
 * @property int|null $drafts_count
 * @property int|null $game_reviews_count
 * @property-read BlogCategory $category
 * @property-read Picture|null $coverPicture
 * @property-read Collection|BlogPostContent[] $contents
 * @property-read BlogPostDraft|null $draft
 * @property-read GameReview|null $gameReview
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title_translation_key_id',
        'type',
        'status',
        'category_id',
        'cover_picture_id',
        'published_at',
    ];

    protected $casts = [
        'slug' => 'string',
        'type' => 'string',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function titleTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'title_translation_key_id');
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
     * @return HasMany<BlogPostContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(BlogPostContent::class)->orderBy('order');
    }

    /**
     * @return HasOne<BlogPostDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(BlogPostDraft::class);
    }

    /**
     * @return HasOne<GameReview, $this>
     */
    public function gameReview(): HasOne
    {
        return $this->hasOne(GameReview::class);
    }

    /**
     * Scope a query to only include published posts.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope a query to only include posts from a specific category.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to only include posts of a specific type.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
