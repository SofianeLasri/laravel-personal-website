<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogPostDraftFactory extends Factory
{
    protected $model = BlogPostDraft::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'blog_post_id' => null, // Usually null for new drafts
            'title_translation_key_id' => TranslationKey::factory()->withTranslations()->create(),
            'slug' => Str::slug($title).'-'.uniqid(),
            'type' => $this->faker->randomElement(['standard', 'game_review']),
            'category_id' => BlogCategory::factory(),
            'cover_picture_id' => Picture::factory(),
        ];
    }

    public function forBlogPost(BlogPost $blogPost): static
    {
        return $this->state([
            'blog_post_id' => $blogPost->id,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category_id' => $blogPost->category_id,
            'cover_picture_id' => $blogPost->cover_picture_id,
        ]);
    }

    public function gameReview(): static
    {
        return $this->state([
            'type' => 'game_review',
        ]);
    }

    public function standard(): static
    {
        return $this->state([
            'type' => 'standard',
        ]);
    }
}
