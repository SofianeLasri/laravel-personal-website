<?php

namespace App\Models;

use Database\Factories\TranslationKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property int|null $translations_count
 * @property-read Translation[] $translations
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

    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    public static function findOrCreateByKey(string $key): self
    {
        return self::firstOrCreate(['key' => $key]);
    }
}
