<?php

namespace Database\Factories;

use App\Models\SocialMediaLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialMediaLinkFactory extends Factory
{
    protected $model = SocialMediaLink::class;

    public function definition(): array
    {
        return [
            'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/></svg>',
            'name' => $this->faker->name(),
            'url' => $this->faker->url(),
        ];
    }
}
