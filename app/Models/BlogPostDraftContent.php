<?php

namespace App\Models;

use Database\Factories\BlogPostDraftContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $blog_post_draft_id
 * @property string $content_type
 * @property int $content_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $blog_post_drafts_count
 * @property-read BlogPostDraft $blogPostDraft
 * @property-read Model $content
 */
class BlogPostDraftContent extends Model
{
    /** @use HasFactory<BlogPostDraftContentFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_draft_id',
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
     * @return BelongsTo<BlogPostDraft, $this>
     */
    public function blogPostDraft(): BelongsTo
    {
        return $this->belongsTo(BlogPostDraft::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}
