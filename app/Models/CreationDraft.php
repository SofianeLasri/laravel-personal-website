<?php

namespace App\Models;

use App\Enums\CreationType;
use App\Services\CreationConversionService;
use Database\Factories\CreationDraftFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $logo_id
 * @property int|null $cover_image_id
 * @property CreationType $type
 * @property Carbon $started_at
 * @property string|null $ended_at
 * @property int|null $short_description_translation_key_id
 * @property int|null $full_description_translation_key_id
 * @property string|null $external_url
 * @property string|null $source_code_url
 * @property bool $featured
 * @property int|null $original_creation_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $original_creations_count
 * @property int|null $logos_count
 * @property int|null $cover_images_count
 * @property int|null $short_description_translation_keys_count
 * @property int|null $full_description_translation_keys_count
 * @property int|null $features_count
 * @property int|null $screenshots_count
 * @property int|null $technologies_count
 * @property int|null $people_count
 * @property int|null $tags_count
 * @property-read Creation|null $originalCreation
 * @property-read Picture|null $logo
 * @property-read Picture|null $coverImage
 * @property-read TranslationKey|null $shortDescriptionTranslationKey
 * @property-read TranslationKey|null $fullDescriptionTranslationKey
 * @property-read Collection|CreationDraftFeature[] $features
 * @property-read Collection|CreationDraftScreenshot[] $screenshots
 * @property-read Collection|Technology[] $technologies
 * @property-read Collection|Person[] $people
 * @property-read Collection|Tag[] $tags
 *
 * @method static CreationDraftFactory<self> factory($count = null, $state = [])
 */
class CreationDraft extends Model
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
        'original_creation_id',
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

    public function originalCreation(): BelongsTo
    {
        return $this->belongsTo(Creation::class, 'original_creation_id');
    }

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
        return $this->hasMany(CreationDraftFeature::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(CreationDraftScreenshot::class);
    }

    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'creation_draft_technology');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'creation_draft_person');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'creation_draft_tag');
    }

    public function getShortDescription(string $locale): string
    {
        if ($this->shortDescriptionTranslationKey) {
            return Translation::trans($this->shortDescriptionTranslationKey->key, $locale);
        }

        return '';
    }

    public function getFullDescription(string $locale): string
    {
        if ($this->fullDescriptionTranslationKey) {
            return Translation::trans($this->fullDescriptionTranslationKey->key, $locale);
        }

        return '';
    }

    /**
     * Create a draft from an existing creation
     */
    public static function fromCreation(Creation $creation): self
    {
        $draft = self::create([
            'name' => $creation->name,
            'slug' => $creation->slug,
            'logo_id' => $creation->logo_id,
            'cover_image_id' => $creation->cover_image_id,
            'type' => $creation->type,
            'started_at' => $creation->started_at,
            'ended_at' => $creation->ended_at,
            'short_description_translation_key_id' => $creation->short_description_translation_key_id,
            'full_description_translation_key_id' => $creation->full_description_translation_key_id,
            'external_url' => $creation->external_url,
            'source_code_url' => $creation->source_code_url,
            'featured' => $creation->featured,
            'original_creation_id' => $creation->id,
        ]);

        foreach ($creation->features as $feature) {
            CreationDraftFeature::create([
                'creation_draft_id' => $draft->id,
                'title_translation_key_id' => $feature->title_translation_key_id,
                'description_translation_key_id' => $feature->description_translation_key_id,
                'picture_id' => $feature->picture_id,
            ]);
        }

        foreach ($creation->screenshots as $screenshot) {
            CreationDraftScreenshot::create([
                'creation_draft_id' => $draft->id,
                'picture_id' => $screenshot->picture_id,
                'caption_translation_key_id' => $screenshot->caption_translation_key_id,
            ]);
        }

        $draft->technologies()->attach($creation->technologies()->pluck('technologies.id'));
        $draft->people()->attach($creation->people()->pluck('people.id'));
        $draft->tags()->attach($creation->tags()->pluck('tags.id'));

        return $draft;
    }

    /**
     * Create a new Creation from this draft
     *
     * @throws ValidationException
     */
    public function toCreation(): Creation
    {
        return app(CreationConversionService::class)->convertDraftToCreation($this);
    }

    /**
     * Update an existing Creation with this draft's data
     *
     * @throws ValidationException
     */
    public function updateCreation(Creation $creation): Creation
    {
        return app(CreationConversionService::class)->updateCreationFromDraft($this, $creation);
    }
}
