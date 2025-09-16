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

    public function withTranslations(array $customTranslations = []): static
    {
        return $this->afterCreating(function (TranslationKey $translationKey) use ($customTranslations) {
            $translationKey->translations()->create([
                'locale' => 'en',
                'text' => $customTranslations['en'] ?? $this->faker->sentence(),
            ]);

            $translationKey->translations()->create([
                'locale' => 'fr',
                'text' => $customTranslations['fr'] ?? $this->faker->sentence(),
            ]);
        });
    }
}
