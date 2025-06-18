<?php

namespace App\Models;

use Database\Factories\TranslationFactory;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $translation_key_id
 * @property string $locale
 * @property string $text
 * @property mixed $use_factory
 * @property mixed $key
 * @property int $translation_keys_count
 * @property-read TranslationKey $translationKey
 */
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'translation_key_id',
        'key',
        'locale',
        'text',
    ];

    protected $casts = [
        'translation_key_id' => 'integer',
        'locale' => 'string',
        'text' => 'string',
    ];

    const LOCALES = ['en', 'fr'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($translation) {
            if (self::where('translation_key_id', $translation->translation_key_id)
                ->where('locale', $translation->locale)
                ->exists()) {
                throw new Exception('Une traduction pour cette clé et cette langue existe déjà.');
            }
        });
    }

    /**
     * @return Attribute<string, string>
     */
    public function key(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->translationKey->key,
            set: function (string $value) {
                $translationKey = TranslationKey::firstOrCreate(['key' => $value]);

                return ['translation_key_id' => $translationKey->id];
            }
        );
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class);
    }

    /**
     * Create or update a translation.
     *
     * @param  string|TranslationKey  $key  The key string or the TranslationKey instance.
     * @param  string  $locale  'en' or 'fr'
     * @param  string  $text  The translation text.
     */
    public static function createOrUpdate(string|TranslationKey $key, string $locale, string $text): self
    {
        if (is_string($key)) {
            $translationKey = TranslationKey::firstOrCreate(['key' => $key]);
        } else {
            $translationKey = $key;
        }

        $translation = self::where('translation_key_id', $translationKey->id)
            ->where('locale', $locale)
            ->first();

        if ($translation) {
            $translation->text = $text;
            $translation->save();

            return $translation;
        }

        return self::create([
            'translation_key_id' => $translationKey->id,
            'locale' => $locale,
            'text' => $text,
        ]);
    }
}
