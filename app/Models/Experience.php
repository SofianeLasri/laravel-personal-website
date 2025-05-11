<?php

namespace App\Models;

use App\Enums\ExperienceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $title_translation_key_id
 * @property string $organization_name
 * @property int|null $logo_id
 * @property \App\Enums\ExperienceType $type
 * @property string $location
 * @property string|null $website_url
 * @property int $short_description_translation_key_id
 * @property int $full_description_translation_key_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $title_translation_keys_count
 * @property int|null $short_description_translation_keys_count
 * @property int|null $full_description_translation_keys_count
 * @property int|null $logos_count
 * @property int|null $technologies_count
 * @property-read \App\Models\TranslationKey|null $titleTranslationKey
 * @property-read \App\Models\TranslationKey|null $shortDescriptionTranslationKey
 * @property-read \App\Models\TranslationKey|null $fullDescriptionTranslationKey
 * @property-read Picture $logo
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Technology[] $technologies
 *
 * @method static \Database\Factories\ExperienceFactory<self> factory($count = null, $state = [])
 */
class Experience extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_translation_key_id',
        'organization_name',
        'logo_id',
        'type',
        'location',
        'website_url',
        'short_description_translation_key_id',
        'full_description_translation_key_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'type' => ExperienceType::class,
        'started_at' => 'date',
        'ended_at' => 'date',
        'website_url' => 'string',
        'organization_name' => 'string',
        'location' => 'string',
    ];

    public function titleTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'title_translation_key_id');
    }

    public function shortDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'short_description_translation_key_id');
    }

    public function fullDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'full_description_translation_key_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'logo_id');
    }

    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }

    public function getTitle(string $locale): string
    {
        return Translation::trans($this->titleTranslationKey->key, $locale);
    }

    public function getShortDescription(string $locale): string
    {
        return Translation::trans($this->shortDescriptionTranslationKey->key, $locale);
    }

    public function getFullDescription(string $locale): string
    {
        return Translation::trans($this->fullDescriptionTranslationKey->key, $locale);
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null;
    }

    public static function ofType(ExperienceType $type)
    {
        return self::where('type', $type);
    }

    public static function latest()
    {
        return self::orderBy('started_at', 'desc');
    }
}
