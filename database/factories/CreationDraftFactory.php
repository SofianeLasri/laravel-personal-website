<?php

namespace Database\Factories;

use App\Enums\CreationType;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CreationDraftFactory extends Factory
{
    protected $model = CreationDraft::class;

    public function definition(): array
    {
        $name = $this->faker->catchPhrase();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'logo_id' => Picture::factory(),
            'cover_image_id' => Picture::factory(),
            'type' => $this->faker->randomElement(CreationType::values()),
            'started_at' => $this->faker->date(),
            'ended_at' => $this->faker->optional(0.7)->date(),
            'short_description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'full_description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'external_url' => $this->faker->optional(0.8)->url(),
            'source_code_url' => $this->faker->optional(0.6)->url(),
            'featured' => $this->faker->boolean(20),
            'original_creation_id' => null,

            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function withFeatures(int $count = 3): static
    {
        return $this->afterCreating(function (CreationDraft $creationDraft) use ($count) {
            $creationDraft->features()->createMany(
                CreationDraftFeature::factory()->count($count)->make()->toArray()
            );
        });
    }

    public function withScreenshots(int $count = 4): static
    {
        return $this->afterCreating(function (CreationDraft $creationDraft) use ($count) {
            CreationDraftScreenshot::factory()
                ->count($count)
                ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
                ->create([
                    'creation_draft_id' => $creationDraft->id,
                ]);
        });
    }

    public function withTechnologies(int $count = 5): static
    {
        return $this->afterCreating(function (CreationDraft $creationDraft) use ($count) {
            $technologies = Technology::factory()->count($count)->create();
            $creationDraft->technologies()->attach($technologies);
        });
    }

    public function withPeople(int $count = 2): static
    {
        return $this->afterCreating(function (CreationDraft $creationDraft) use ($count) {
            $people = Person::factory()->count($count)->create();
            $creationDraft->people()->attach($people);
        });
    }

    public function withTags(int $count = 3): static
    {
        return $this->afterCreating(function (CreationDraft $creationDraft) use ($count) {
            $tags = Tag::factory()->count($count)->create();
            $creationDraft->tags()->attach($tags);
        });
    }
}
