<?php

namespace Database\Factories;

use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CreationDraftScreenshotFactory extends Factory
{
    protected $model = CreationDraftScreenshot::class;

    public function definition(): array
    {
        return [
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'creation_draft_id' => CreationDraft::factory(),
            'picture_id' => Picture::factory(),
            'caption_translation_key_id' => $this->faker->optional(0.7)->randomElement([TranslationKey::factory()->withTranslations()->create()]),
        ];
    }

    public function withCaption(): self
    {
        return $this->state([
            'caption_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
        ]);
    }
}
