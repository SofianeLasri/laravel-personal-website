<?php

namespace Database\Factories;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class OptimizedPictureFactory extends Factory
{
    protected $model = OptimizedPicture::class;

    public function definition(): array
    {
        return [
            'variant' => $this->faker->randomElement(OptimizedPicture::VARIANTS),
            'path' => $this->createDummyImage(),
            'format' => $this->faker->randomElement(OptimizedPicture::FORMATS),

            'picture_id' => Picture::factory(),
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
}
