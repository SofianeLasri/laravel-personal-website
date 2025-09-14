<?php

namespace App\Models;

use Database\Factories\BlogCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $icon
 * @property string|null $color
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $blog_posts_count
 * @property-read Collection|BlogPost[] $blogPosts
 */
class BlogCategory extends Model
{
    /** @use HasFactory<BlogCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_translation_key_id',
        'color',
        'order',
    ];

    protected $casts = [
        'slug' => 'string',
        'color' => 'string', // TODO: Use predefined set of colors
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function nameTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'name_translation_key_id');
    }

    /**
     * @return HasMany<BlogPost, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
