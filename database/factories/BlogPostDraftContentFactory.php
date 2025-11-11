<?php

namespace Database\Factories;

use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostDraftContentFactory extends Factory
{
    protected $model = BlogPostDraftContent::class;

    public function definition(): array
    {
        $contentTypes = [
            ContentMarkdown::class,
            ContentGallery::class,
            ContentVideo::class,
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
            'content_type' => ContentMarkdown::class,
            'content_id' => ContentMarkdown::factory(),
        ]);
    }

    public function gallery(): static
    {
        return $this->state([
            'content_type' => ContentGallery::class,
            'content_id' => ContentGallery::factory(),
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'content_type' => ContentVideo::class,
            'content_id' => ContentVideo::factory(),
        ]);
    }

    public function forBlogPostDraft(BlogPostDraft $draft): static
    {
        return $this->state([
            'blog_post_draft_id' => $draft->id,
        ]);
    }
}
