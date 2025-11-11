<?php

namespace Database\Factories;

use App\Models\CustomEmoji;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomEmojiFactory extends Factory
{
    protected $model = CustomEmoji::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->lexify('emoji_???'),
            'picture_id' => Picture::factory(),
        ];
    }
}
