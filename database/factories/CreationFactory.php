<?php

namespace Database\Factories;

use App\Enums\CreationType;
use App\Models\Creation;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CreationFactory extends Factory
{
    protected $model = Creation::class;

    public function definition(): array
    {
        $name = $this->faker->catchPhrase();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'logo_id' => Picture::factory(),
            'cover_image_id' => Picture::factory(),
            'type' => $this->faker->randomElement(CreationType::values()),
            'started_at' => $this->faker->dateTimeBetween('-5 years', '-6 months'),
            'ended_at' => $this->faker->optional(0.8)->dateTimeBetween('-5 months', 'now'),
            'short_description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'full_description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'external_url' => $this->faker->optional(0.7)->url(),
            'source_code_url' => $this->faker->optional(0.5)->url(),
            'featured' => $this->faker->boolean(20), // 20% chance to be featured
        ];
    }

    public function withFeatures(int $count = 3): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            Feature::factory()->count($count)->create([
                'creation_id' => $creation->id,
            ]);
        });
    }

    public function withScreenshots(int $count = 4): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            Screenshot::factory()->count($count)->create([
                'creation_id' => $creation->id,
            ]);
        });
    }

    public function withTechnologies(int $count = 5): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            $technologies = Technology::factory()->count($count)->create();
            $creation->technologies()->attach($technologies);
        });
    }

    public function withPeople(int $count = 2): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            $people = Person::factory()->count($count)->create();
            $creation->people()->attach($people);
        });
    }

    public function withTags(int $count = 3): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            $tags = Tag::factory()->count($count)->create();
            $creation->tags()->attach($tags);
        });
    }

    public function featured(): static
    {
        return $this->state([
            'featured' => true,
        ]);
    }
}
