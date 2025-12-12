<?php

namespace Database\Factories;

use App\Models\BlogPostDraft;
use App\Models\BlogPostPreviewToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogPostPreviewTokenFactory extends Factory
{
    protected $model = BlogPostPreviewToken::class;

    public function definition(): array
    {
        return [
            'token' => Str::random(32),
            'blog_post_draft_id' => BlogPostDraft::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDays(),
        ]);
    }

    public function expiresIn(int $days): static
    {
        return $this->state([
            'expires_at' => now()->addDays($days),
        ]);
    }
}
