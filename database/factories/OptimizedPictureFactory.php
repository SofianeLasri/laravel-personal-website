<?php

namespace Database\Factories;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

class OptimizedPictureFactory extends Factory
{
    protected $model = OptimizedPicture::class;

    public function definition(): array
    {
        return [
            'variant' => $this->faker->word(),
            'path' => $this->faker->word(),
            'format' => $this->faker->word(),

            'picture_id' => Picture::factory(),
        ];
    }
}
