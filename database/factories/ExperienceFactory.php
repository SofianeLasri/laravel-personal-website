<?php

namespace Database\Factories;

use App\Enums\ExperienceType;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExperienceFactory extends Factory
{
    protected $model = Experience::class;

    public function definition(): array
    {
        $isFormation = $this->faker->boolean();

        return [
            'title_translation_key_id' => TranslationKey::factory(),
            'organization_name' => $isFormation
                ? $this->faker->randomElement(['Université Paris Saclay', 'Efrei Paris'])
                : $this->faker->company(),
            'logo_id' => Picture::factory(),
            'type' => $isFormation ? ExperienceType::FORMATION : ExperienceType::EMPLOI,
            'location' => $this->faker->city().', '.$this->faker->country(),
            'website_url' => $this->faker->optional(0.8)->url(),
            'short_description_translation_key_id' => TranslationKey::factory(),
            'full_description_translation_key_id' => TranslationKey::factory(),
            'started_at' => $this->faker->dateTimeBetween('-10 years', '-2 years'),
            'ended_at' => $this->faker->optional(0.7)->dateTimeBetween('-2 years', 'now'),
        ];
    }

    public function formation(): static
    {
        return $this->state([
            'type' => ExperienceType::FORMATION,
            'organization_name' => $this->faker->randomElement(['Université de Paris', 'École Polytechnique', 'HEC Paris', 'ENS', 'ESCP Business School']),
        ]);
    }

    public function emploi(): static
    {
        return $this->state([
            'type' => ExperienceType::EMPLOI,
            'organization_name' => $this->faker->company(),
        ]);
    }

    public function ongoing(): static
    {
        return $this->state([
            'ended_at' => null,
        ]);
    }

    public function withTechnologies(int $count = 3): static
    {
        return $this->afterCreating(function (Experience $experience) use ($count) {
            $technologies = Technology::factory()->count($count)->create();
            $experience->technologies()->attach($technologies);
        });
    }
}
