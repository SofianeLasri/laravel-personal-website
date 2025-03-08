<?php

namespace App\Models;

use App\Enums\CreationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Creation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_id',
        'cover_image_id',
        'type',
        'started_at',
        'ended_at',
        'short_description_translation_key_id',
        'full_description_translation_key_id',
        'external_url',
        'source_code_url',
        'featured',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'type' => CreationType::class,
        'started_at' => 'date',
        'ended_at' => 'date:Y-m-d',
        'external_url' => 'string',
        'source_code_url' => 'string',
        'featured' => 'boolean',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'logo_id');
    }

    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_image_id');
    }

    public function shortDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'short_description_translation_key_id');
    }

    public function fullDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'full_description_translation_key_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class);
    }

    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getShortDescription(string $locale): string
    {
        return Translation::trans($this->shortDescriptionTranslationKey->key, $locale);
    }

    public function getFullDescription(string $locale): string
    {
        return Translation::trans($this->fullDescriptionTranslationKey->key, $locale);
    }
}
