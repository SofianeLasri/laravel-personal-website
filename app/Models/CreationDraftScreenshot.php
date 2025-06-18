<?php

namespace App\Models;

use Database\Factories\CreationDraftScreenshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $creation_draft_id
 * @property int $picture_id
 * @property int|null $caption_translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creation_drafts_count
 * @property int|null $pictures_count
 * @property int|null $caption_translation_keys_count
 * @property-read CreationDraft $creationDraft
 * @property-read Picture $picture
 * @property-read TranslationKey|null $captionTranslationKey
 */
class CreationDraftScreenshot extends Model
{
    /** @use HasFactory<CreationDraftScreenshotFactory> */
    use HasFactory;

    protected $fillable = [
        'creation_draft_id',
        'picture_id',
        'caption_translation_key_id',
    ];

    /**
     * @return BelongsTo<CreationDraft, $this>
     */
    public function creationDraft(): BelongsTo
    {
        return $this->belongsTo(CreationDraft::class);
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function captionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'caption_translation_key_id');
    }
}
