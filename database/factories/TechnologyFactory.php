<?php

namespace Database\Factories;

use App\Enums\TechnologyType;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

class TechnologyFactory extends Factory
{
    protected $model = Technology::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'icon_picture_id' => Picture::factory()->create()->id,
            'type' => $this->faker->randomElement(TechnologyType::values()),
            'description_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
        ];
    }

    public function framework(): static
    {
        $names = [
            'Laravel',
            'Symfony',
            'Django',
            'Flask',
            'Ruby on Rails',
            'Express.js',
            'Spring',
            'Angular',
            'React',
            'Vue.js',
            'NestJS',
        ];

        return $this->state([
            'name' => $this->faker->unique()->randomElement($names),
            'type' => TechnologyType::FRAMEWORK,
        ]);
    }

    public function library(): static
    {
        $names = [
            'jQuery',
            'Bootstrap',
            'Tailwind CSS',
            'Lodash',
            'Moment.js',
            'Axios',
            'Chart.js',
            'Three.js',
            'Socket.IO',
            'Redux',
            'Vuex',
            'RxJS',
        ];

        return $this->state([
            'name' => $this->faker->unique()->randomElement($names),
            'type' => TechnologyType::LIBRARY,
        ]);
    }

    public function language(): static
    {
        $names = [
            'JavaScript',
            'Python',
            'Java',
            'C#',
            'PHP',
            'Ruby',
            'Go',
            'Swift',
            'Kotlin',
            'TypeScript',
            'Rust',
            'C++',
        ];

        return $this->state([
            'name' => $this->faker->unique()->randomElement($names),
            'type' => TechnologyType::LANGUAGE,
        ]);
    }

    public function gameEngine(): static
    {
        $names = [
            'Unity',
            'Unreal Engine',
            'Godot',
            'CryEngine',
            'Amazon Lumberyard',
            'Bevy',
            'Source Engine',
            'Source 2',
        ];

        return $this->state([
            'name' => $this->faker->unique()->randomElement($names),
            'type' => TechnologyType::GAME_ENGINE,
        ]);
    }

    public function withOptimizedIcon(): static
    {
        return $this->afterCreating(function (Technology $technology) {
            if ($technology->iconPicture) {
                $this->createOptimizedPicturesFor($technology->iconPicture);
            }
        });
    }

    public function complete(): static
    {
        return $this->afterCreating(function (Technology $technology) {
            if ($technology->iconPicture) {
                $this->createOptimizedPicturesFor($technology->iconPicture);
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

    /**
     * Create a complete set of technologies for testing purposes.
     */
    public function createSet(): Collection
    {
        $technologies = collect([
            $this->language()->complete()->create(['name' => 'PHP']),
            $this->framework()->complete()->create(['name' => 'Laravel']),
            $this->language()->complete()->create(['name' => 'JavaScript']),
            $this->framework()->complete()->create(['name' => 'Vue.js']),
            $this->library()->complete()->create(['name' => 'Tailwind CSS']),
            $this->language()->complete()->create(['name' => 'TypeScript']),
            $this->framework()->complete()->create(['name' => 'React']),
            $this->gameEngine()->complete()->create(['name' => 'Unity']),
        ]);

        return $technologies;
    }
}
