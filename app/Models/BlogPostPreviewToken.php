<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token
 * @property int $blog_post_draft_id
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BlogPostDraft $blogPostDraft
 */
class BlogPostPreviewToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'blog_post_draft_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<BlogPostDraft, $this>
     */
    public function blogPostDraft(): BelongsTo
    {
        return $this->belongsTo(BlogPostDraft::class);
    }

    /**
     * Generate a unique preview token
     */
    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Scope to get only valid (not expired) tokens
     *
     * @param  Builder<BlogPostPreviewToken>  $query
     * @return Builder<BlogPostPreviewToken>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get the preview URL for this token
     */
    public function getPreviewUrl(): string
    {
        return route('public.blog.preview', ['token' => $this->token]);
    }

    /**
     * Create or update a preview token for a blog post draft
     */
    public static function createForDraft(BlogPostDraft $draft, int $expiresInDays = 7): self
    {
        // Delete existing tokens for this draft
        self::where('blog_post_draft_id', $draft->id)->delete();

        // Create new token
        return self::create([
            'token' => self::generateUniqueToken(),
            'blog_post_draft_id' => $draft->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);
    }
}
