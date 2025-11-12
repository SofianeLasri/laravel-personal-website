<?php

namespace Database\Factories;

use App\Enums\CreationType;
use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\Feature;
use App\Models\OptimizedPicture;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\TranslationKey;
use App\Models\Video;
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
            Screenshot::factory()
                ->count($count)
                ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
                ->create([
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

    public function withExistingTechnologies(array $technologies): static
    {
        return $this->afterCreating(function (Creation $creation) use ($technologies) {
            $creation->technologies()->attach($technologies);
        });
    }

    /**
     * Complete the creation with a realistic set of related models:
     * - 3 to 5 technologies (existing or new)
     * - 2 to 4 features
     * - 2 to 4 screenshots (with optimized pictures)
     * - 0 to 3 people
     * - 2 to 4 tags
     *
     * @return $this
     */
    public function complete(): static
    {
        return $this->afterCreating(function (Creation $creation) {
            $technologies = Technology::count() >= 5
                ? Technology::inRandomOrder()->take(rand(3, 5))->get()
                : Technology::factory()->count(5)->create();

            $creation->technologies()->attach($technologies);

            Feature::factory()->count(rand(2, 4))->create([
                'creation_id' => $creation->id,
            ]);

            $screenshotCount = rand(2, 4);
            $screenshots = Screenshot::factory()
                ->count($screenshotCount)
                ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
                ->create([
                    'creation_id' => $creation->id,
                ]);

            foreach ([$creation->logo, $creation->coverImage] as $picture) {
                if ($picture) {
                    $this->createOptimizedPicturesFor($picture);
                }
            }

            foreach ($screenshots as $screenshot) {
                if ($screenshot->picture) {
                    $this->createOptimizedPicturesFor($screenshot->picture);
                }
            }

            if (rand(0, 1)) {
                $people = Person::factory()->count(rand(1, 3))->create();
                $creation->people()->attach($people);
            }

            $tags = Tag::factory()->count(rand(2, 4))->create();
            $creation->tags()->attach($tags);
        });
    }

    /**
     * Create content blocks for the creation.
     *
     * @param  int  $count  Number of markdown content blocks to create
     * @return $this
     */
    public function withContentBlocks(int $count = 1): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $contentMarkdown = ContentMarkdown::factory()->create();

                CreationContent::create([
                    'creation_id' => $creation->id,
                    'content_type' => ContentMarkdown::class,
                    'content_id' => $contentMarkdown->id,
                    'order' => $i + 1,
                ]);
            }
        });
    }

    protected function createOptimizedPicturesFor(Picture $picture): void
    {
        $formats = ['avif', 'webp', 'jpg'];
        $variants = ['thumbnail', 'small', 'medium', 'large', 'full'];

        foreach ($formats as $format) {
            foreach ($variants as $variant) {
                OptimizedPicture::create([
                    'picture_id' => $picture->id,
                    'format' => $format,
                    'variant' => $variant,
                    'path' => "uploads/optimized/{$picture->filename}_{$variant}.{$format}",
                ]);
            }
        }
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

    public function withReadyVideos(int $count = 2): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            $videos = Video::factory()->readyAndPublic()->count($count)->create();
            $creation->videos()->attach($videos);
        });
    }

    public function withTranscodingVideos(int $count = 2): static
    {
        return $this->afterCreating(function (Creation $creation) use ($count) {
            $videos = Video::factory()->transcodingAndPrivate()->count($count)->create();
            $creation->videos()->attach($videos);
        });
    }

    public function featured(): static
    {
        return $this->state([
            'featured' => true,
        ]);
    }
}
