<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Technology extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'svg_icon',
        'name_translation_key_id',
        'description_translation_key_id',
    ];

    protected $casts = [
        'name' => 'string',
        'svg_icon' => 'string',
    ];

    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    public function nameTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'name_translation_key_id');
    }

    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }

    public function getDescription(string $locale): string
    {
        return Translation::trans($this->descriptionTranslationKey->key, $locale);
    }
}
