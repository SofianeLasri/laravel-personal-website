<?php

namespace Database\Factories;

use App\Models\ContentVideo;
use App\Models\TranslationKey;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentVideoFactory extends Factory
{
    protected $model = ContentVideo::class;

    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'caption_translation_key_id' => $this->faker->optional(0.7)->passthrough(
                TranslationKey::factory()->withTranslations()
            ),
        ];
    }

    public function withCaption(): static
    {
        return $this->state([
            'caption_translation_key_id' => TranslationKey::factory()->withTranslations(),
        ]);
    }

    public function withoutCaption(): static
    {
        return $this->state([
            'caption_translation_key_id' => null,
        ]);
    }
}
