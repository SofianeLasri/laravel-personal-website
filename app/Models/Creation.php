<?php

namespace App\Models;

use App\Enums\CreationType;
use Database\Factories\CreationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $logo_id
 * @property int|null $cover_image_id
 * @property CreationType $type
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $short_description_translation_key_id
 * @property int $full_description_translation_key_id
 * @property string|null $external_url
 * @property string|null $source_code_url
 * @property bool $featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $logos_count
 * @property int|null $cover_images_count
 * @property int|null $short_description_translation_keys_count
 * @property int|null $full_description_translation_keys_count
 * @property int|null $features_count
 * @property int|null $screenshots_count
 * @property int|null $technologies_count
 * @property int|null $people_count
 * @property int|null $tags_count
 * @property int|null $drafts_count
 * @property-read Picture|null $logo
 * @property-read Picture|null $coverImage
 * @property-read TranslationKey|null $shortDescriptionTranslationKey
 * @property-read TranslationKey|null $fullDescriptionTranslationKey
 * @property-read Collection|Feature[] $features
 * @property-read Collection|Screenshot[] $screenshots
 * @property-read Collection|Technology[] $technologies
 * @property-read Collection|Person[] $people
 * @property-read Collection|Tag[] $tags
 * @property-read Collection|CreationDraft[] $drafts
 *
 * @method static CreationFactory<self> factory($count = null, $state = [])
 */
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
        'ended_at' => 'date',
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

    public function drafts(): HasMany
    {
        return $this->hasMany(CreationDraft::class, 'original_creation_id', 'id');
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
