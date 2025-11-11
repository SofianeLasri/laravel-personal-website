<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogPostContentFactory extends Factory
{
    protected $model = BlogPostContent::class;

    public function definition(): array
    {
        $contentTypes = [
            ContentMarkdown::class,
            ContentGallery::class,
            ContentVideo::class,
        ];

        $contentType = $this->faker->randomElement($contentTypes);

        return [
            'blog_post_id' => BlogPost::factory(),
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

    public function forBlogPost(BlogPost $blogPost): static
    {
        return $this->state([
            'blog_post_id' => $blogPost->id,
        ]);
    }
}
