<?php

namespace Database\Factories;

use App\Models\BlogContentGallery;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlogContentGalleryFactory extends Factory
{
    protected $model = BlogContentGallery::class;

    public function definition(): array
    {
        return [
            // Simple gallery without layout configuration
        ];
    }

    public function withPictures(int $count = 3): static
    {
        return $this->afterCreating(function (BlogContentGallery $gallery) use ($count) {
            $pictures = Picture::factory()->count($count)->create();

            foreach ($pictures as $index => $picture) {
                $gallery->pictures()->attach($picture->id, [
                    'order' => $index + 1,
                    'caption_translation_key_id' => null,
                ]);
            }
        });
    }
}
