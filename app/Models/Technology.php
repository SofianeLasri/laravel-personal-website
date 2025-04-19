<?php

namespace App\Models;

use App\Enums\TechnologyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Technology extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'svg_icon',
        'description_translation_key_id',
    ];

    protected $casts = [
        'name' => 'string',
        'svg_icon' => 'string',
        'type' => TechnologyType::class,
    ];

    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }

    public function getDescription(string $locale): string
    {
        return Translation::trans($this->descriptionTranslationKey->key, $locale);
    }

    public function creationDrafts(): BelongsToMany
    {
        return $this->belongsToMany(CreationDraft::class, 'creation_draft_technology', 'technology_id', 'creation_draft_id');
    }
}
