<?php

namespace Database\Factories;

use App\Models\Creation;
use App\Models\Feature;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        return [
            'creation_id' => Creation::factory(),
            'title_translation_key_id' => TranslationKey::factory(),
            'description_translation_key_id' => TranslationKey::factory(),
            'picture_id' => $this->faker->boolean(70) ? Picture::factory() : null,
        ];
    }
}
