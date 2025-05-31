<?php

namespace Database\Factories;

use App\Models\Picture;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'path' => $this->faker->filePath(),
            'bunny_video_id' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'cover_picture_id' => Picture::factory(),
        ];
    }
}
