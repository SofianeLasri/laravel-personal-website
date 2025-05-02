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
 * @property-read CreationDraft|null $creationDraft
 * @property-read Picture|null $picture
 * @property-read TranslationKey|null $captionTranslationKey
 *
 * @method static CreationDraftScreenshotFactory<self> factory($count = null, $state = [])
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
