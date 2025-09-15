<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $name = $this->faker->word();

        return [
            'slug' => Str::slug($name).'-'.uniqid(),
            'name_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'color' => $this->faker->optional(0.8)->hexColor(), // TODO: Use predefined set of colors
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Create a category with specific names for different locales
     *
     * @param  array<string, string>  $names  Array with locale as key and name as value, e.g., ['fr' => 'Technologie', 'en' => 'Technology']
     */
    public function withNames(array $names): static
    {
        return $this->state(function (array $attributes) use ($names): array {
            // Use French name for slug if available, otherwise use the first name
            $frenchName = $names['fr'] ?? (string) reset($names);
            $slug = Str::slug($frenchName).'-'.uniqid();

            return [
                'slug' => $slug,
                'name_translation_key_id' => TranslationKey::factory()
                    ->state(['key' => 'blog_category_'.Str::slug($frenchName).'_'.uniqid()])
                    ->afterCreating(function ($translationKey) use ($names) {
                        /** @var TranslationKey $translationKey */
                        // Create translations for specified locales
                        foreach ($names as $locale => $name) {
                            $translationKey->translations()->create([
                                'locale' => $locale,
                                'text' => $name,
                            ]);
                        }

                        $supportedLocales = ['fr', 'en'];
                        foreach ($supportedLocales as $locale) {
                            if (! array_key_exists($locale, $names)) {
                                $translationKey->translations()->create([
                                    'locale' => $locale,
                                    'text' => '',
                                ]);
                            }
                        }
                    })
                    ->create(),
            ];
        });
    }

    /**
     * Create a category with a specific name for French locale
     *
     * @param  string|null  $englishName  Optional English translation
     */
    public function withFrenchName(string $frenchName, ?string $englishName = null): static
    {
        $names = ['fr' => $frenchName];
        if ($englishName !== null) {
            $names['en'] = $englishName;
        }

        return $this->withNames($names);
    }

    /**
     * Create a category with a specific name for English locale
     *
     * @param  string|null  $frenchName  Optional French translation
     */
    public function withEnglishName(string $englishName, ?string $frenchName = null): static
    {
        $names = ['en' => $englishName];
        if ($frenchName !== null) {
            $names['fr'] = $frenchName;
        }

        return $this->withNames($names);
    }
}
