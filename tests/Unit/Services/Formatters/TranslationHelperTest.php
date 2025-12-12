<?php

namespace Tests\Unit\Services\Formatters;

use App\Models\Translation;
use App\Services\Formatters\TranslationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TranslationHelper::class)]
class TranslationHelperTest extends TestCase
{
    #[Test]
    public function it_returns_translation_for_current_locale(): void
    {
        $helper = new TranslationHelper('fr', 'en');

        $translations = new Collection([
            $this->makeTranslation('en', 'English text'),
            $this->makeTranslation('fr', 'Texte français'),
        ]);

        $result = $helper->getWithFallback($translations);

        $this->assertEquals('Texte français', $result);
    }

    #[Test]
    public function it_falls_back_to_fallback_locale_when_current_not_found(): void
    {
        $helper = new TranslationHelper('de', 'en');

        $translations = new Collection([
            $this->makeTranslation('en', 'English text'),
            $this->makeTranslation('fr', 'Texte français'),
        ]);

        $result = $helper->getWithFallback($translations);

        $this->assertEquals('English text', $result);
    }

    #[Test]
    public function it_returns_empty_string_when_no_translation_found(): void
    {
        $helper = new TranslationHelper('de', 'es');

        $translations = new Collection([
            $this->makeTranslation('en', 'English text'),
            $this->makeTranslation('fr', 'Texte français'),
        ]);

        $result = $helper->getWithFallback($translations);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_returns_empty_string_for_empty_collection(): void
    {
        $helper = new TranslationHelper('en', 'en');

        $translations = new Collection([]);

        $result = $helper->getWithFallback($translations);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_does_not_fallback_when_locale_equals_fallback_locale(): void
    {
        $helper = new TranslationHelper('en', 'en');

        $translations = new Collection([
            $this->makeTranslation('en', 'English text'),
        ]);

        $result = $helper->getWithFallback($translations);

        $this->assertEquals('English text', $result);
    }

    #[Test]
    #[DataProvider('dateFormattingProvider')]
    public function it_formats_dates_correctly(
        Carbon|string|null $date,
        string $locale,
        ?string $expected,
    ): void {
        $helper = new TranslationHelper($locale, 'en');

        $result = $helper->formatDate($date);

        $this->assertEquals($expected, $result);
    }

    public static function dateFormattingProvider(): array
    {
        return [
            'carbon object in french' => [
                Carbon::create(2024, 1, 15),
                'fr',
                'Janvier 2024',
            ],
            'carbon object in english' => [
                Carbon::create(2024, 6, 20),
                'en',
                'June 2024',
            ],
            'string date' => [
                '2024-03-10',
                'fr',
                'Mars 2024',
            ],
            'null date returns null' => [
                null,
                'fr',
                null,
            ],
        ];
    }

    #[Test]
    public function it_capitalizes_month_name(): void
    {
        $helper = new TranslationHelper('fr', 'en');

        $result = $helper->formatDate(Carbon::create(2024, 1, 1));

        $this->assertStringStartsWith('J', $result);
        $this->assertEquals('Janvier 2024', $result);
    }

    #[Test]
    public function it_creates_instance_from_app_locale(): void
    {
        app()->setLocale('fr');
        config(['app.fallback_locale' => 'en']);

        $helper = TranslationHelper::fromAppLocale();

        $this->assertEquals('fr', $helper->getLocale());
        $this->assertEquals('en', $helper->getFallbackLocale());
    }

    #[Test]
    public function it_exposes_locale_getters(): void
    {
        $helper = new TranslationHelper('fr', 'en');

        $this->assertEquals('fr', $helper->getLocale());
        $this->assertEquals('en', $helper->getFallbackLocale());
    }

    /**
     * Create a mock Translation object for testing.
     */
    private function makeTranslation(string $locale, string $text): Translation
    {
        $translation = new Translation;
        $translation->locale = $locale;
        $translation->text = $text;

        return $translation;
    }
}
