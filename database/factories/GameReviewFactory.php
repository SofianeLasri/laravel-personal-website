<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\GameReview;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameReviewFactory extends Factory
{
    protected $model = GameReview::class;

    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'game_title' => $this->faker->words(3, true),
            'release_date' => $this->faker->optional(0.8)->date(),
            'genre' => $this->faker->optional(0.7)->randomElement([
                'Action', 'Adventure', 'RPG', 'Strategy', 'Simulation',
                'Sports', 'Racing', 'Fighting', 'Puzzle', 'Platform',
            ]),
            'developer' => $this->faker->optional(0.8)->company(),
            'publisher' => $this->faker->optional(0.8)->company(),
            'platforms' => $this->faker->optional(0.9)->randomElements([
                'PC', 'PlayStation 5', 'PlayStation 4', 'Xbox Series X/S',
                'Xbox One', 'Nintendo Switch', 'Steam Deck', 'Mobile',
            ], $this->faker->numberBetween(1, 4)),
            'cover_picture_id' => Picture::factory(),
            'pros_translation_key_id' => $this->faker->optional(0.8)->passthrough(
                TranslationKey::factory()->withTranslations()
            ),
            'cons_translation_key_id' => $this->faker->optional(0.8)->passthrough(
                TranslationKey::factory()->withTranslations()
            ),
            'rating' => $this->faker->optional(0.8)->randomElement(['positive', 'negative']),
        ];
    }

    public function withProsAndCons(): static
    {
        return $this->state([
            'pros_translation_key_id' => TranslationKey::factory()->withTranslations(),
            'cons_translation_key_id' => TranslationKey::factory()->withTranslations(),
        ]);
    }

    public function forBlogPost(BlogPost $blogPost): static
    {
        return $this->state([
            'blog_post_id' => $blogPost->id,
        ]);
    }
}
