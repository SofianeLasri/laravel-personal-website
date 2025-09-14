<?php

namespace Database\Factories;

use App\Models\BlogContentMarkdown;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogContentMarkdownFactory extends Factory
{
    protected $model = BlogContentMarkdown::class;

    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory()->withTranslations(),
        ];
    }
}
