<?php

namespace App\Models;

use App\Enums\CreationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     */
    public function toCreation(): Creation
    {
        if (! $this->short_description_translation_key_id) {
            $shortDescKey = TranslationKey::create(['key' => 'creation.'.$this->slug.'.short_description']);
            $this->update(['short_description_translation_key_id' => $shortDescKey->id]);
        }

        if (! $this->full_description_translation_key_id) {
            $fullDescKey = TranslationKey::create(['key' => 'creation.'.$this->slug.'.full_description']);
            $this->update(['full_description_translation_key_id' => $fullDescKey->id]);
        }

        $creation = Creation::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_id' => $this->logo_id,
            'cover_image_id' => $this->cover_image_id,
            'type' => $this->type,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'short_description_translation_key_id' => $this->short_description_translation_key_id,
            'full_description_translation_key_id' => $this->full_description_translation_key_id,
            'external_url' => $this->external_url,
            'source_code_url' => $this->source_code_url,
            'featured' => $this->featured,
        ]);

        foreach ($this->features as $draftFeature) {
            Feature::create([
                'creation_id' => $creation->id,
                'title_translation_key_id' => $draftFeature->title_translation_key_id,
                'description_translation_key_id' => $draftFeature->description_translation_key_id,
                'picture_id' => $draftFeature->picture_id,
            ]);
        }

        foreach ($this->screenshots as $draftScreenshot) {
            Screenshot::create([
                'creation_id' => $creation->id,
                'picture_id' => $draftScreenshot->picture_id,
                'caption_translation_key_id' => $draftScreenshot->caption_translation_key_id,
            ]);
        }

        $creation->technologies()->attach($this->technologies()->pluck('technologies.id'));
        $creation->people()->attach($this->people()->pluck('people.id'));
        $creation->tags()->attach($this->tags()->pluck('tags.id'));

        return $creation;
    }

    /**
     * Update an existing Creation with this draft's data
     */
    public function updateCreation(Creation $creation): Creation
    {
        $creation->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_id' => $this->logo_id,
            'cover_image_id' => $this->cover_image_id,
            'type' => $this->type,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'short_description_translation_key_id' => $this->short_description_translation_key_id,
            'full_description_translation_key_id' => $this->full_description_translation_key_id,
            'external_url' => $this->external_url,
            'source_code_url' => $this->source_code_url,
            'featured' => $this->featured,
        ]);

        $creation->features()->delete();

        foreach ($this->features as $draftFeature) {
            Feature::create([
                'creation_id' => $creation->id,
                'title_translation_key_id' => $draftFeature->title_translation_key_id,
                'description_translation_key_id' => $draftFeature->description_translation_key_id,
                'picture_id' => $draftFeature->picture_id,
            ]);
        }

        $creation->screenshots()->delete();

        foreach ($this->screenshots as $draftScreenshot) {
            Screenshot::create([
                'creation_id' => $creation->id,
                'picture_id' => $draftScreenshot->picture_id,
                'caption_translation_key_id' => $draftScreenshot->caption_translation_key_id,
            ]);
        }

        $creation->technologies()->sync($this->technologies()->pluck('technologies.id'));
        $creation->people()->sync($this->people()->pluck('people.id'));
        $creation->tags()->sync($this->tags()->pluck('tags.id'));

        return $creation;
    }

    public function technologies2(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'creation_draft_technology', 'creation_draft_id', 'technology_id');
    }

    public function people2(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'creation_draft_person', 'creation_draft_id', 'person_id');
    }

    public function tags2(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'creation_draft_tag', 'creation_draft_id', 'tag_id');
    }
}
