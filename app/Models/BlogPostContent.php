<?php

namespace App\Models;

use Database\Factories\BlogPostContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $blog_post_id
 * @property string $content_type
 * @property int $content_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $blog_posts_count
 * @property-read BlogPost $blogPost
 * @property-read Model $content
 */
class BlogPostContent extends Model
{
    /** @use HasFactory<BlogPostContentFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_id',
        'content_type',
        'content_id',
        'order',
    ];

    protected $casts = [
        'content_type' => 'string',
        'content_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}
