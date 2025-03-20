<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
