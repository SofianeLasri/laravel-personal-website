<?php

namespace Database\Factories;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostDraftContentFactory extends Factory
{
    protected $model = BlogPostDraftContent::class;

    public function definition(): array
    {
        $contentTypes = [
            BlogContentMarkdown::class,
            BlogContentGallery::class,
            BlogContentVideo::class,
        ];

        $contentType = $this->faker->randomElement($contentTypes);

        return [
            'blog_post_draft_id' => BlogPostDraft::factory(),
            'content_type' => $contentType,
            'content_id' => $contentType::factory(),
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function markdown(): static
    {
        return $this->state([
            'content_type' => BlogContentMarkdown::class,
            'content_id' => BlogContentMarkdown::factory(),
        ]);
    }

    public function gallery(): static
    {
        return $this->state([
            'content_type' => BlogContentGallery::class,
            'content_id' => BlogContentGallery::factory(),
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'content_type' => BlogContentVideo::class,
            'content_id' => BlogContentVideo::factory(),
        ]);
    }

    public function forBlogPostDraft(BlogPostDraft $draft): static
    {
        return $this->state([
            'blog_post_draft_id' => $draft->id,
        ]);
    }
}
