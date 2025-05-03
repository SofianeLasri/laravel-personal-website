<?php

namespace App\Models;

use Database\Factories\CreationDraftFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $creation_draft_id
 * @property int $title_translation_key_id
 * @property int $description_translation_key_id
 * @property int|null $picture_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creation_drafts_count
 * @property int|null $title_translation_keys_count
 * @property int|null $description_translation_keys_count
 * @property int|null $pictures_count
 * @property-read CreationDraft|null $creationDraft
 * @property-read TranslationKey|null $titleTranslationKey
 * @property-read TranslationKey|null $descriptionTranslationKey
 * @property-read Picture|null $picture
 *
 * @method static CreationDraftFeatureFactory<self> factory($count = null, $state = [])
 */
class CreationDraftFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'creation_draft_id',
        'title_translation_key_id',
        'description_translation_key_id',
        'picture_id',
    ];

    public function creationDraft(): BelongsTo
    {
        return $this->belongsTo(CreationDraft::class);
    }

    public function titleTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'title_translation_key_id');
    }

    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    public function getTitle(string $locale): string
    {
        return Translation::trans($this->titleTranslationKey->key, $locale);
    }

    public function getDescription(string $locale): string
    {
        return Translation::trans($this->descriptionTranslationKey->key, $locale);
    }
}
