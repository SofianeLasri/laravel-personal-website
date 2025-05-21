<?php

namespace App\Models;

use Database\Factories\ScreenshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $creation_id
 * @property int $picture_id
 * @property int|null $caption_translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $creations_count
 * @property int|null $pictures_count
 * @property int|null $caption_translation_keys_count
 * @property-read Creation $creation
 * @property-read Picture $picture
 * @property-read TranslationKey|null $captionTranslationKey
 */
class Screenshot extends Model
{
    /** @use HasFactory<ScreenshotFactory> */
    use HasFactory;

    protected $fillable = [
        'creation_id',
        'picture_id',
        'caption_translation_key_id',
    ];

    /**
     * @return BelongsTo<Creation, $this>
     */
    public function creation(): BelongsTo
    {
        return $this->belongsTo(Creation::class);
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

    public function getCaption(string $locale): string
    {
        if ($this->captionTranslationKey) {
            return $this->captionTranslationKey->translations()->where('locale', $locale)->value('text');
        }

        return '';
    }
}
