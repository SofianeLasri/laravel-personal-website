<?php

namespace Database\Factories;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

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
            'path_original' => $this->createDummyImage(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    private function createDummyImage(): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->create(128, 128)->fill('F78E57');
        $path = 'uploads/'.uniqid().'.jpg';
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        return $path;
    }

    public function withOptimizedPicture(string $variant, string $format): PictureFactory
    {
        return $this->afterCreating(function (Picture $picture) use ($variant, $format) {
            OptimizedPicture::factory()->create([
                'picture_id' => $picture->id,
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
                    OptimizedPicture::factory()->create([
                        'picture_id' => $picture->id,
                        'variant' => $variant,
                        'format' => $format,
                    ]);
                }
            }
        });
    }
}
