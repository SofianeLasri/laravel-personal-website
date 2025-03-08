<?php

namespace Database\Factories;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->slug(),
        ];
    }

    public function withTranslations(): static
    {
        return $this->afterCreating(function (TranslationKey $translationKey) {
            $translationKey->translations()->create([
                'locale' => 'en',
                'text' => $this->faker->sentence(),
            ]);

            $translationKey->translations()->create([
                'locale' => 'fr',
                'text' => $this->faker->sentence(),
            ]);
        });
    }
}
