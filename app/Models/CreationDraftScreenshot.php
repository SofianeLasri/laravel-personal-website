<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $creation_draft_id
 * @property int $picture_id
 * @property int|null $caption_translation_key_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creation_drafts_count
 * @property int|null $pictures_count
 * @property int|null $caption_translation_keys_count
 * @property-read \App\Models\CreationDraft|null $creationDraft
 * @property-read \App\Models\Picture|null $picture
 * @property-read \App\Models\TranslationKey|null $captionTranslationKey
 *
 * @method static \Database\Factories\CreationDraftScreenshotFactory<self> factory($count = null, $state = [])
 */
class CreationDraftScreenshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'creation_draft_id',
        'picture_id',
        'caption_translation_key_id',
    ];

    public function creationDraft(): BelongsTo
    {
        return $this->belongsTo(CreationDraft::class);
    }

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    public function captionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'caption_translation_key_id');
    }

    public function getCaption(string $locale): string
    {
        if ($this->captionTranslationKey()->exists()) {
            return $this->captionTranslationKey->translations()->where('locale', $locale)->value('text');
        }

        return '';
    }
}
