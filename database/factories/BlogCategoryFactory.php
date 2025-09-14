<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $name = $this->faker->word();

        return [
            'slug' => Str::slug($name).'-'.uniqid(),
            'icon' => $this->faker->optional(0.7)->randomElement([
                'fas fa-gamepad',
                'fas fa-code',
                'fas fa-paint-brush',
                'fas fa-music',
                'fas fa-camera',
            ]),
            'color' => $this->faker->optional(0.8)->hexColor(),
            'order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
