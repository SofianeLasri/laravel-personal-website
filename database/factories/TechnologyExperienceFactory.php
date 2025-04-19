<?php

namespace Database\Factories;

use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnologyExperienceFactory extends Factory
{
    protected $model = TechnologyExperience::class;

    public function definition(): array
    {
        return [

            'technology_id' => Technology::factory(),
            'description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
        ];
    }
}
