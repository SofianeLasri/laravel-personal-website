<?php

namespace Database\Factories;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PictureFactory extends Factory
{
    protected $model = Picture::class;

    public function definition(): array
    {
        return [
            'filename' => $this->faker->word(),
            'width' => $this->faker->randomNumber(),
            'height' => $this->faker->randomNumber(),
            'size' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function withOptimizedPicture(string $variant, string $format): PictureFactory
    {
        return $this->afterCreating(function (Picture $picture) use ($variant, $format) {
            $picture->optimizedPictures()->create([
                'variant' => $variant,
                'format' => $format,
            ]);
        });
    }

    public function withOptimizedPictures(): PictureFactory
    {
        return $this->afterCreating(function (Picture $picture) {
            foreach (OptimizedPicture::VARIANTS as $variant) {
                foreach (OptimizedPicture::FORMATS as $format) {
                    $picture->optimizedPictures()->create([
                        'variant' => $variant,
                        'format' => $format,
                    ]);
                }
            }
        });
    }
}
