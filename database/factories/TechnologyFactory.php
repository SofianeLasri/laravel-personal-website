<?php

namespace Database\Factories;

use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnologyFactory extends Factory
{
    protected $model = Technology::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
            'name_translation_key_id' => TranslationKey::factory(),
            'description_translation_key_id' => TranslationKey::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Technology $technology) {
            // Create translations for name
            Translation::createOrUpdate(
                $technology->nameTranslationKey->key,
                'fr',
                $this->faker->word()
            );
            Translation::createOrUpdate(
                $technology->nameTranslationKey->key,
                'en',
                $this->faker->word()
            );

            // Create translations for description
            Translation::createOrUpdate(
                $technology->descriptionTranslationKey->key,
                'fr',
                $this->faker->sentence()
            );
            Translation::createOrUpdate(
                $technology->descriptionTranslationKey->key,
                'en',
                $this->faker->sentence()
            );
        });
    }
}
