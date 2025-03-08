<?php

namespace Database\Factories;

use App\Models\Creation;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScreenshotFactory extends Factory
{
    protected $model = Screenshot::class;

    public function definition(): array
    {
        return [
            'creation_id' => Creation::factory(),
            'picture_id' => Picture::factory(),
            'caption_translation_key_id' => $this->faker->boolean(80)
                ? TranslationKey::factory()
                : null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Screenshot $screenshot) {
            if ($screenshot->caption_translation_key_id) {
                // Create translations for caption
                Translation::createOrUpdate(
                    $screenshot->captionTranslationKey->key,
                    'fr',
                    $this->faker->sentence()
                );
                Translation::createOrUpdate(
                    $screenshot->captionTranslationKey->key,
                    'en',
                    $this->faker->sentence()
                );
            }
        });
    }
}
