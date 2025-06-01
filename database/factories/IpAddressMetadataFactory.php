<?php

namespace Database\Factories;

use App\Models\IpAddressMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;

class IpAddressMetadataFactory extends Factory
{
    protected $model = IpAddressMetadata::class;

    public function definition(): array
    {
        return [
            'country_code' => $this->faker->randomElement(IpAddressMetadata::COUNTRY_CODES),
            'lat' => $this->faker->latitude(),
            'lon' => $this->faker->longitude(),

            'ip_address_id' => IpAddress::factory(),
        ];
    }
}
