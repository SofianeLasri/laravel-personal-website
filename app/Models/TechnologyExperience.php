<?php

namespace App\Models;

use Database\Factories\TechnologyExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $technology_id
 * @property int $description_translation_key_id
 * @property int|null $technologies_count
 * @property int|null $description_translation_keys_count
 * @property-read Technology $technology
 * @property-read TranslationKey $descriptionTranslationKey
 */
class TechnologyExperience extends Model
{
    /** @use HasFactory<TechnologyExperienceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'technology_id',
        'description_translation_key_id',
    ];

    /**
     * @return BelongsTo<TechnologyExperience, $this>
     */
    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }

    /**
     * @return BelongsTo<TechnologyExperience, $this>
     */
    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }
}
