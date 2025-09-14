<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogContentMarkdown;
use App\Models\BlogPost;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'slug' => Str::slug($title).'-'.uniqid(),
            'type' => $this->faker->randomElement(['article', 'game_review']),
            'status' => $this->faker->randomElement(['draft', 'published']),
            'category_id' => BlogCategory::factory(),
            'cover_picture_id' => Picture::factory(),
            'published_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function gameReview(): static
    {
        return $this->state([
            'type' => 'game_review',
        ]);
    }

    public function article(): static
    {
        return $this->state([
            'type' => 'article',
        ]);
    }

    public function withContent(): static
    {
        return $this->afterCreating(function (BlogPost $post) {
            $translationKey = TranslationKey::factory()->withTranslations()->create();
            $markdown = BlogContentMarkdown::create([
                'translation_key_id' => $translationKey->id,
            ]);

            $post->contents()->create([
                'content_type' => BlogContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 1,
            ]);
        });
    }
}
