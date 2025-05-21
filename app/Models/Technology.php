<?php

namespace App\Models;

use App\Enums\TechnologyType;
use Database\Factories\TechnologyFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $creations_count
 * @property int|null $description_translation_keys_count
 * @property int|null $creation_drafts_count
 * @property-read Collection|Creation[] $creations
 * @property-read TranslationKey $descriptionTranslationKey
 * @property-read Collection|CreationDraft[] $creationDrafts
 *
 * @method static Builder|Technology framework()
 * @method static Builder|Technology library()
 * @method static Builder|Technology language()
 * @method static Builder|Technology other()
 */
class Technology extends Model
{
    /** @use HasFactory<TechnologyFactory> */
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

    /**
     * @return BelongsToMany<Creation, $this>
     */
    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }

    /**
     * @return BelongsToMany<CreationDraft, $this>
     */
    public function creationDrafts(): BelongsToMany
    {
        return $this->belongsToMany(CreationDraft::class, 'creation_draft_technology', 'technology_id', 'creation_draft_id');
    }

    public function getDescription(string $locale): string
    {
        return Translation::trans($this->descriptionTranslationKey->key, $locale);
    }
}
