<?php

namespace App\Models;

use App\Enums\ExperienceType;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $title_translation_key_id
 * @property string $organization_name
 * @property int $logo_id
 * @property ExperienceType $type
 * @property string $location
 * @property string|null $website_url
 * @property int $short_description_translation_key_id
 * @property int $full_description_translation_key_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $title_translation_keys_count
 * @property int|null $short_description_translation_keys_count
 * @property int|null $full_description_translation_keys_count
 * @property int|null $logos_count
 * @property int|null $technologies_count
 * @property-read TranslationKey $titleTranslationKey
 * @property-read TranslationKey $shortDescriptionTranslationKey
 * @property-read TranslationKey $fullDescriptionTranslationKey
 * @property-read Picture $logo
 * @property-read Collection|Technology[] $technologies
 */
class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
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

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function titleTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'title_translation_key_id');
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function shortDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'short_description_translation_key_id');
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function fullDescriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'full_description_translation_key_id');
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'logo_id');
    }

    /**
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class);
    }
}
