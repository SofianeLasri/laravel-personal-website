<?php

namespace App\Services\Formatters;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TranslationHelper
{
    public function __construct(
        private string $locale,
        private string $fallbackLocale,
    ) {}

    /**
     * Create an instance with the current application locale settings.
     */
    public static function fromAppLocale(): self
    {
        return new self(
            app()->getLocale(),
            config('app.fallback_locale'),
        );
    }

    /**
     * Get a translation with fallback to the fallback locale.
     *
     * @param  Collection<int, Translation>  $translations  Collection of translations
     * @return string The translation text or empty string if not found
     */
    public function getWithFallback(Collection $translations): string
    {
        $translation = $translations->where('locale', $this->locale)->first();
        if ($translation && isset($translation->text)) {
            return $translation->text;
        }

        if ($this->locale !== $this->fallbackLocale) {
            $fallbackTranslation = $translations->where('locale', $this->fallbackLocale)->first();
            if ($fallbackTranslation && isset($fallbackTranslation->text)) {
                return $fallbackTranslation->text;
            }
        }

        return '';
    }

    /**
     * Format a date according to the locale with month in CamelCase.
     *
     * @param  string|Carbon|null  $date  The date to format
     * @return string|null Formatted date (e.g., "Janvier 2024") or null
     */
    public function formatDate(Carbon|string|null $date): ?string
    {
        if (! $date) {
            return null;
        }

        /** @var Carbon $carbonDate */
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        $carbonDate->locale($this->locale);
        $month = Str::ucfirst($carbonDate->translatedFormat('F'));

        return $month.' '.$carbonDate->format('Y');
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }
}
