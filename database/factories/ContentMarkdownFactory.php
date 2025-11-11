<?php

namespace Database\Factories;

use App\Models\ContentMarkdown;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentMarkdownFactory extends Factory
{
    protected $model = ContentMarkdown::class;

    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory()->withTranslations(),
        ];
    }
}
