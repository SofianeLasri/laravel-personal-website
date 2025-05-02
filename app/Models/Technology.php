<?php

namespace App\Models;

use App\Enums\TechnologyType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property TechnologyType $type
 * @property string $svg_icon
 * @property int $description_translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creations_count
 * @property int|null $description_translation_keys_count
 * @property int|null $creation_drafts_count
 * @property-read Collection|Creation[] $creations
 * @property-read TranslationKey|null $descriptionTranslationKey
 * @property-read Collection|CreationDraft[] $creationDrafts
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Technology framework()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Technology library()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Technology language()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Technology other()
 * @method static \Database\Factories\TechnologyFactory<self> factory($count = null, $state = [])
 */
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

    public function scopeFramework($query)
    {
        return $query->where('type', TechnologyType::FRAMEWORK);
    }

    public function scopeLibrary($query)
    {
        return $query->where('type', TechnologyType::LIBRARY);
    }

    public function scopeLanguage($query)
    {
        return $query->where('type', TechnologyType::LANGUAGE);
    }

    public function scopeOther($query)
    {
        return $query->where('type', TechnologyType::OTHER);
    }
}
