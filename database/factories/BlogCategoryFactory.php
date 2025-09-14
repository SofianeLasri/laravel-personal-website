<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $name = $this->faker->word();

        return [
            'slug' => Str::slug($name).'-'.uniqid(),
            'name_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'color' => $this->faker->optional(0.8)->hexColor(), // TODO: Use predefined set of colors
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
