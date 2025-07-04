<?php

namespace App\Models;

use Database\Factories\TranslationKeyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property mixed $use_factory
 * @property int $translations_count
 * @property-read Translation[]|Collection $translations
 */
class TranslationKey extends Model
{
    /** @use HasFactory<TranslationKeyFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
    ];

    protected $casts = [
        'key' => 'string',
    ];

    protected static function booted(): void
    {
        static::deleting(function (TranslationKey $translationKey) {
            $translationKey->translations()->delete();
        });
    }

    /**
     * @return HasMany<Translation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
