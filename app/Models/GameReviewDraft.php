<?php

namespace App\Models;

use Database\Factories\GameReviewDraftFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $blog_post_draft_id
 * @property string $game_title
 * @property Carbon|null $release_date
 * @property string|null $genre
 * @property string|null $developer
 * @property string|null $publisher
 * @property array|null $platforms
 * @property int|null $cover_picture_id
 * @property int|null $pros_translation_key_id
 * @property int|null $cons_translation_key_id
 * @property float|null $score
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $blog_post_drafts_count
 * @property int|null $cover_pictures_count
 * @property int|null $pros_translation_keys_count
 * @property int|null $cons_translation_keys_count
 * @property int|null $links_count
 * @property-read BlogPostDraft $blogPostDraft
 * @property-read Picture|null $coverPicture
 * @property-read TranslationKey|null $prosTranslationKey
 * @property-read TranslationKey|null $consTranslationKey
 * @property-read Collection|GameReviewDraftLink[] $links
 */
class GameReviewDraft extends Model
{
    /** @use HasFactory<GameReviewDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'blog_post_draft_id',
        'game_title',
        'release_date',
        'genre',
        'developer',
        'publisher',
        'platforms',
        'cover_picture_id',
        'pros_translation_key_id',
        'cons_translation_key_id',
        'score',
    ];

    protected $casts = [
        'game_title' => 'string',
        'release_date' => 'date',
        'genre' => 'string',
        'developer' => 'string',
        'publisher' => 'string',
        'platforms' => 'array',
        'score' => 'decimal:1',
    ];

    /**
     * @return BelongsTo<BlogPostDraft, $this>
     */
    public function blogPostDraft(): BelongsTo
    {
        return $this->belongsTo(BlogPostDraft::class);
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function prosTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'pros_translation_key_id');
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function consTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'cons_translation_key_id');
    }

    /**
     * @return HasMany<GameReviewDraftLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(GameReviewDraftLink::class)->orderBy('order');
    }
}
