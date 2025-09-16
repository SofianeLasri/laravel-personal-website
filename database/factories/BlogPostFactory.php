<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogContentGallery;
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
            'title_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'type' => $this->faker->randomElement(['article', 'game_review', 'tutorial']),
            'category_id' => BlogCategory::factory(),
            'cover_picture_id' => Picture::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state([
            // Published posts don't need additional state - created_at serves as publication date
        ]);
    }

    public function draft(): static
    {
        return $this->state([
            // Draft posts don't need additional state
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

    public function withCompleteContent(): static
    {
        return $this->afterCreating(function (BlogPost $post) {
            $order = 1;

            // Create first markdown section (introduction)
            $introTranslationKey = TranslationKey::factory()->withTranslations([
                'fr' => $this->faker->paragraphs(3, true),
                'en' => $this->faker->paragraphs(3, true),
            ])->create();

            $introMarkdown = BlogContentMarkdown::create([
                'translation_key_id' => $introTranslationKey->id,
            ]);

            $post->contents()->create([
                'content_type' => BlogContentMarkdown::class,
                'content_id' => $introMarkdown->id,
                'order' => $order++,
            ]);

            // Create image gallery section
            $gallery = BlogContentGallery::factory()->withPictures(4)->create();

            $post->contents()->create([
                'content_type' => BlogContentGallery::class,
                'content_id' => $gallery->id,
                'order' => $order++,
            ]);

            // Create second markdown section (conclusion)
            $conclusionTranslationKey = TranslationKey::factory()->withTranslations([
                'fr' => $this->faker->paragraphs(2, true),
                'en' => $this->faker->paragraphs(2, true),
            ])->create();

            $conclusionMarkdown = BlogContentMarkdown::create([
                'translation_key_id' => $conclusionTranslationKey->id,
            ]);

            $post->contents()->create([
                'content_type' => BlogContentMarkdown::class,
                'content_id' => $conclusionMarkdown->id,
                'order' => $order,
            ]);
        });
    }
}
