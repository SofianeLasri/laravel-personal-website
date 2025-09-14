<?php

namespace App\Models;

use Database\Factories\BlogContentVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $video_id
 * @property int|null $caption_translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $videos_count
 * @property int|null $caption_translation_keys_count
 * @property-read Video $video
 * @property-read TranslationKey|null $captionTranslationKey
 */
class BlogContentVideo extends Model
{
    /** @use HasFactory<BlogContentVideoFactory> */
    use HasFactory;

    protected $fillable = [
        'video_id',
        'caption_translation_key_id',
    ];

    protected $casts = [
        'video_id' => 'integer',
        'caption_translation_key_id' => 'integer',
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
}
