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
 * @property int|null $translations_count
 * @property-read Collection|Translation[] $translations
 *
 * @method static TranslationKeyFactory<self> factory($count = null, $state = [])
 */
class TranslationKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
    ];

    protected $casts = [
        'key' => 'string',
    ];

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
