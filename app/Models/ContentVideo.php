<?php

namespace App\Models;

use Database\Factories\ContentVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $video_id
 * @property int|null $caption_translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $videos_count
 * @property int|null $caption_translation_keys_count
 * @property-read Video|null $video
 * @property-read TranslationKey|null $captionTranslationKey
 */
class ContentVideo extends Model
{
    /** @use HasFactory<ContentVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'video_id',
        'caption_translation_key_id',
    ];

    protected $casts = [
        'video_id' => 'integer',
        'caption_translation_key_id' => 'integer',
    ];

    protected $with = [
        'video',
        'captionTranslationKey.translations',
    ];

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function captionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'caption_translation_key_id');
    }

    /**
     * @return MorphOne<BlogPostDraftContent, $this>
     */
    public function blogContent(): MorphOne
    {
        return $this->morphOne(BlogPostDraftContent::class, 'content');
    }
}
