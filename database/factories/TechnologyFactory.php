<?php

namespace Database\Factories;

use App\Enums\TechnologyType;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnologyFactory extends Factory
{
    protected $model = Technology::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'svg_icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
            'type' => $this->faker->randomElement(TechnologyType::values()),
            'description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
        ];
    }

    public function framework(): static
    {
        return $this->state([
            'type' => TechnologyType::FRAMEWORK,
        ]);
    }

    public function library(): static
    {
        return $this->state([
            'type' => TechnologyType::LIBRARY,
        ]);
    }

    public function language(): static
    {
        return $this->state([
            'type' => TechnologyType::LANGUAGE,
        ]);
    }

    public function featured(): static
    {
        return $this->state([
            'featured' => true,
        ]);
    }
}
