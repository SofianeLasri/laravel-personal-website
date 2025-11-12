<?php

namespace Database\Factories;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreationDraftContentFactory extends Factory
{
    protected $model = CreationDraftContent::class;

    public function definition(): array
    {
        $contentTypes = [
            ContentMarkdown::class,
            ContentGallery::class,
            ContentVideo::class,
        ];

        $contentType = $this->faker->randomElement($contentTypes);

        return [
            'creation_draft_id' => CreationDraft::factory(),
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

    public function forCreationDraft(CreationDraft $draft): static
    {
        return $this->state([
            'creation_draft_id' => $draft->id,
        ]);
    }
}
