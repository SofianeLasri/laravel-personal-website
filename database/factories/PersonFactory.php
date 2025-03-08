<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'picture_id' => Picture::factory(),
        ];
    }
}
