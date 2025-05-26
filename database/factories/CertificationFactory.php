<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'score' => $this->faker->word(),
            'date' => Carbon::now(),
            'link' => $this->faker->word(),

            'picture_id' => Picture::factory(),
        ];
    }
}
