<?php

namespace Database\Factories;

use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'locale' => $this->faker->randomElement(Translation::LOCALES),
            'text' => $this->faker->text(),

            'translation_key_id' => TranslationKey::factory(),
        ];
    }
}
