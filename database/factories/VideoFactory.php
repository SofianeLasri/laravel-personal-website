<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
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
            'status' => $this->faker->randomElement(VideoStatus::cases()),
            'visibility' => $this->faker->randomElement(VideoVisibility::cases()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'cover_picture_id' => Picture::factory(),
        ];
    }

    /**
     * @return Factory<Video>
     */
    public function readyAndPublic(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
        ]);
    }

    /**
     * @return Factory<Video>
     */
    public function transcodingAndPrivate(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::TRANSCODING,
            'visibility' => VideoVisibility::PRIVATE,
        ]);
    }
}
