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
 * @property mixed $use_factory
 * @property int|null $technologies_count
 * @property int|null $description_translation_keys_count
 * @property-read Technology|null $technology
 * @property-read TranslationKey|null $descriptionTranslationKey
 *
 * @method static TechnologyExperienceFactory<self> factory($count = null, $state = [])
 */
class TechnologyExperience extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'technology_id',
        'description_translation_key_id',
    ];

    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }

    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }
}
