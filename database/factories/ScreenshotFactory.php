<?php

namespace Database\Factories;

use App\Models\Creation;
use App\Models\Picture;
use App\Models\Screenshot;
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
                ? TranslationKey::factory()->withTranslations()->create()
                : null,
        ];
    }
}
